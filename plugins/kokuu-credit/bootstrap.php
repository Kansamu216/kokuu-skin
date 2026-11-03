<?php

/*
 * KokuuSkin 积分（kokuu-credit）
 *
 * 皮肤站的积分账本与签到功能。
 *
 * 【唯一账本】全站只有 users.score 一个余额字段，所有加减都通过
 *   kokuu_credit_ledger() 记入 credit_ledger 流水表，带 event_id 唯一索引
 *   保证幂等（同一事件重推多少次，余额只加一次）。
 *
 * 【余额原子性】用数据库层 increment/decrement 而非「读出来算完再写回」，
 *   避免并发请求读到同一旧余额导致丢更新。
 *
 * 【签到已独立】签到功能已拆分到 kokuu-signin 插件，
 *   本插件只负责积分账本与调分接口。
 *
 * 【管理平台接口】/api/admin/* 供 KokuuPanel 等服务端到服务端调用，
 *   用 HMAC-SHA256 签名（时间戳 + nonce + 过期时间），密钥存 options 表。
 *
 * ⚠️ 重要：辅助函数必须定义在下方「return 闭包」之**前**。
 *    PHP 的 include/require 一旦在文件顶层遇到 return，就立即停止执行该文件
 *    剩余部分，导致函数永远不被声明（Call to undefined function）。
 *    此处用 function_exists 包裹，同时防止重复加载时声明冲突。
 */

use App\Models\User;
use App\Services\Hook;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/*
 * ---- Core/Host 分层：服务工厂 ----
 *
 * 积分核心逻辑已抽到 src/Core（可移植，零宿主依赖）+
 * src/Host（BlessingSkin 适配）。下面的 kokuu_credit_* 辅助函数
 * 保留原签名，内部委托给 Core，保持向后兼容。
 */
if (! function_exists('kokuu_credit_service')) {
    function kokuu_credit_service(): \Kokuu\Credit\Core\CreditService
    {
        static $svc = null;
        if ($svc === null) {
            $svc = new \Kokuu\Credit\Core\CreditService(
                new \Kokuu\Credit\Host\DbLedgerRepository(),
                new \Kokuu\Credit\Host\BsAccountRepository()
            );
        }

        return $svc;
    }
}

if (! function_exists('kokuu_credit_config')) {
    /**
     * 读取插件配置（存 options 表，键前缀 kokuu_credit_）。
     */
    function kokuu_credit_config(): array
    {
        $defaults = [
            /*
             * 管理平台（KokuuPanel）用的 HMAC 密钥。
             * 用于 /api/admin/* 这类服务端到服务端接口。
             */
            'admin_secret'   => '',
            // 签名有效期（秒），超出即拒，防重放
            'ttl'            => 300,
        ];

        $options = app('options');
        $config  = [];
        foreach ($defaults as $key => $default) {
            $value = $options->get('kokuu_credit_' . $key);
            $config[$key] = ($value === null || $value === '') ? $default : $value;
        }

        $config['ttl'] = max(30, min(3600, (int) $config['ttl']));

        return $config;
    }
}

if (! function_exists('kokuu_credit_save_config')) {
    function kokuu_credit_save_config(array $payload): void
    {
        $options = app('options');
        foreach ($payload as $key => $value) {
            $options->set('kokuu_credit_' . $key, $value);
        }
    }
}

if (! function_exists('kokuu_credit_ensure_tables')) {
    /**
     * 惰性建表。
     *
     * 为什么不走 Blessing Skin 的 migration 机制：插件被删除时内核不会清理它
     * 建的数据，而 ledger 是**审计数据**，绝不能随插件卸载而消失。用
     * Schema::hasTable 判断一次，代价可忽略。
     */
    function kokuu_credit_ensure_tables(): void
    {
        if (! Schema::hasTable('credit_ledger')) {
            Schema::create('credit_ledger', function (Blueprint $table) {
                $table->increments('id');
                // 幂等键：论坛侧生成，格式形如 "thread:12" / "reply:345"
                $table->string('event_id', 80)->unique();
                $table->unsignedInteger('uid');
                $table->integer('delta');
                $table->integer('balance_after');
                // thread / reply / liked / sign / manual
                $table->string('source', 32);
                $table->string('ref', 191)->nullable();
                $table->string('note', 191)->nullable();
                $table->timestamps();

                $table->index('uid');
                $table->index('created_at');
            });
        }
    }
}

if (! function_exists('kokuu_credit_ledger')) {
    /**
     * 写一条流水并原子累加余额（委托给 Core\CreditService）。
     *
     * 保留此函数是为了向后兼容：抽奖插件、管理端路由等仍调用它。
     * 真正的实现已抽到 src/Core/CreditService + src/Host/DbLedgerRepository，
     * 那段代码可脱离 BlessingSkin 在任意 PHP 项目复用。
     *
     * @return bool true=写入成功；false=event_id 已存在（幂等命中）
     */
    function kokuu_credit_ledger(int $uid, int $delta, string $source, string $eventId, ?string $ref = null, ?string $note = null): bool
    {
        kokuu_credit_ensure_tables();

        $result = kokuu_credit_service()->record($uid, $delta, $source, $eventId, $ref, $note);

        return $result->written;
    }
}

if (! function_exists('kokuu_credit_daily_total')) {
    /**
     * 某用户今日（自然日，按服务器时区）已获得的加分总额。
     * 用于 max_per_day 限额控制。**只统计正数**（加分），因为论坛只发不扣。
     */
    function kokuu_credit_daily_total(int $uid): int
    {
        kokuu_credit_ensure_tables();

        return (int) DB::table('credit_ledger')
            ->where('uid', $uid)
            ->where('delta', '>', 0)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('delta');
    }
}

if (! function_exists('kokuu_credit_admin_verify')) {
    /**
     * 校验**管理平台**调用的签名接口。
     *
     * 与上面的 `kokuu_credit_verify` 是两套独立的东西，刻意不合并：
     *   - 那套给「论坛加分」用：按 email 定位账号，payload 固定为
     *     ts.nonce.event_id.email.delta.source；
     *   - 这套给管理平台用：按 **uid** 定位，payload 因接口而异。
     *
     * 合并成一个函数会让「改一边影响另一边」，而两边的调用方完全不相干 —
     * 论坛改不动平台，平台也不该被论坛的需求牵着走。
     *
     * 为什么平台不能直接用 `kokuu_credit_verify`：它按 **email** 找账号，
     * 而平台只存了 `bs_uid` / 角色名，**没有玩家邮箱**；而且 `earn` 只加分，
     * 管理操作需要能扣分、能清零。
     *
     * 签名：`sign = hash_hmac('sha256', implode('.', [ts, nonce, ...$parts]), secret)`，
     * 与现有那套同风格（点号连接 + 时间戳窗口 + nonce 去重），
     * 这样皮肤站这边能复用同一套心智，不用再发明第二种。
     *
     * @param array $body  请求体
     * @param array $parts 该接口参与签名的业务字段，**顺序必须与平台侧一致**
     *                     （PHP 数组保序，所以 array_values 的顺序就是这里的书写顺序）
     * @return string|null null = 通过；否则是给调用方看的原因
     */
    function kokuu_credit_admin_verify(array $body, array $parts): ?string
    {
        $cfg = kokuu_credit_config();

        // 读取管理平台密钥
        // 让平台不必等第二个密钥配好才能接进来。
        $secret = (string) ($cfg['admin_secret'] ?? '');
        if ($secret === '') {
            $secret = (string) ($cfg['secret'] ?? '');
        }

        if ($secret === '') {
            return '服务端未配置密钥（kokuu_credit_admin_secret）';
        }

        foreach (['ts', 'nonce', 'sign'] as $k) {
            if (! isset($body[$k]) || $body[$k] === '') {
                return "缺少参数 {$k}";
            }
        }

        // 业务字段只要求**存在**，允许空串 ——
        // 例如搜索接口的 kw 为空是正常情况（列出全部），不能当成漏填。
        foreach (array_keys($parts) as $k) {
            if (! array_key_exists($k, $body)) {
                return "缺少参数 {$k}";
            }
        }

        $ts = (int) $body['ts'];
        // 时间戳偏差过大直接拒，这是防重放的第一道闸
        if (abs(time() - $ts) > $cfg['ttl']) {
            return '请求已过期';
        }

        $payload = implode('.', array_merge([$ts, $body['nonce']], array_values($parts)));
        $expected = hash_hmac('sha256', $payload, $secret);

        // 用 hash_equals 防时序攻击
        if (! hash_equals($expected, (string) $body['sign'])) {
            return '签名不正确';
        }

        // nonce 二次去重：同一签名短时间内只能成功一次。
        // 命名空间与论坛那套分开，避免两边 nonce 撞车时互相影响。
        $cacheKey = 'kokuu_credit_admin_nonce_' . md5((string) $body['nonce']);
        if (! \Illuminate\Support\Facades\Cache::add($cacheKey, 1, $cfg['ttl'] + 60)) {
            return '重复请求';
        }

        return null;
    }
}

if (! function_exists('kokuu_credit_stats')) {
    /**
     * 后台概览：总流水数、今日发放、用户数。
     *
     * ⚠️⚠️ `$uid` 一定要传对，否则仪表盘会显示别人的流水。
     *
     * 背景（真实 bug）：这个函数原先**根本不按 uid 过滤**，
     * 于是仪表盘上「今日获得 / 今日笔数」统计的是**全站所有用户**
     * 今天的流水，而不是当前用户的。用户反馈「新注册的账号（初始 1000 分、
     * 没有任何交易）显示今日获得 +22、今日笔数 3」——那 +22/3 是站上
     * 其他人（管理员签到、论坛发帖等）的流水，跟他本人毫无关系。
     *
     * 两个调用场景要的统计口径不同：
     *   - 传 $uid：只看某个用户（仪表盘 `kokuu_credit_dashboard_data`），
     *     用 `auth_user()->uid` 这种「当前登录用户」。
     *   - 不传：全站汇总（后台配置页、流水页、流水 API），
     *     这几个地方本来就要看全站，所以保持默认行为不变。
     *
     * @param int|null $uid 限定某个用户；null = 不限（全站）
     */
    function kokuu_credit_stats(?int $uid = null): array
    {
        kokuu_credit_ensure_tables();

        // 今日发放/笔数：只看 delta>0（加分），可选地限定 uid
        $todayQ = function () use ($uid) {
            $q = DB::table('credit_ledger')
                ->where('delta', '>', 0)
                ->where('created_at', '>=', now()->startOfDay());
            if ($uid !== null) {
                $q->where('uid', $uid);
            }
            return $q;
        };

        $totalQ = DB::table('credit_ledger');
        if ($uid !== null) {
            $totalQ->where('uid', $uid);
        }

        return [
            'total'  => (int) $totalQ->count(),
            'today'  => (int) $todayQ()->sum('delta'),
            'events' => (int) $todayQ()->count(),
        ];
    }
}

if (! function_exists('kokuu_credit_source_labels')) {
    /**
     * 积分来源 → 中文标签的**唯一**映射表。
     *
     * ⚠️ 这是权威定义，其他地方一律调本函数，不要再抄一份数组。
     *    历史教训：曾经有三份副本，`manual` 在一处写「手动调整」、
     *    另两处写「管理员手动」，同一笔流水在仪表盘和流水页显示不同文案。
     */
    function kokuu_credit_source_labels(): array
    {
        return [
            'thread' => '发起主题',
            'reply'  => '回复帖子',
            'liked'  => '帖子被赞',
            'sign'   => '每日签到',
            'manual' => '管理员手动',
            'lottery' => '抽奖',
            'legacy' => '历史补录',
        ];
    }
}

if (! function_exists('kokuu_credit_source_label')) {
    /**
     * 单个来源 → 中文标签。统一走复数版映射表，避免多处副本漂移。
     */
    function kokuu_credit_source_label(string $source): string
    {
        return kokuu_credit_source_labels()[$source] ?? $source;
    }
}

if (! function_exists('kokuu_credit_dashboard_data')) {
    /**
     * 仪表盘「积分」+「社区互联」两个容器所需的全部数据。
     *
     * 一次算完，视图里不再查库 —— 仪表盘不该有 N+1。
     *
     * @param  \App\Models\User|null  $user  当前登录用户；为 null（理论上不会发生）时降级
     */
    function kokuu_credit_dashboard_data(?User $user): array
    {
        // ⚠️ 必须传当前用户 uid —— 不传会统计**全站**今天的流水，
        // 新用户就会看到别人的「今日获得/今日笔数」。见 kokuu_credit_stats() 注释。
        $stats = kokuu_credit_stats($user->uid ?? null);

        // 今日已获得的积分与今日是否已签到是两件事，别混用
        $todayEarned = $stats['today'];

        $recent = [];
        $ledgerOk = true;
        try {
            kokuu_credit_ensure_tables();
            $recent = DB::table('credit_ledger')
                ->where('uid', (int) ($user->uid ?? 0))
                ->orderBy('id', 'desc')
                ->limit(4)
                ->get()
                ->map(fn ($r) => [
                    'delta'   => (int) $r->delta,
                    'after'   => (int) $r->balance_after,
                    'source'  => (string) $r->source,
                    'at'      => (string) $r->created_at,
                ])
                ->all();
        } catch (\Throwable $e) {
            // 积分表还没建好时不应该让管理员打不开后台
            $ledgerOk = false;
        }

        // ⚠️ 不要在这里再抄一份标签表。
        //    曾有三份副本（此处 / kokuu_credit_source_label() / ledger 路由），
        //    结果 manual 被写成「手动调整」vs「管理员手动」两种文案，
        //    同一个来源在仪表盘和流水页显示得不一样。
        //    统一走 kokuu_credit_source_label()，只有一处定义就不会再漂移。
        foreach ($recent as &$r) {
            $r['source_label'] = kokuu_credit_source_label((string) $r['source']);
        }
        unset($r);

        return [
            'score'        => (int) ($user->score ?? 0),
            'todayEarned'  => (int) $todayEarned,
            'todayEvents'  => (int) $stats['events'],
            'recent'       => $recent,
            'ledgerReady'  => $ledgerOk,
        ];
    }
}


return function ($plugin) {
    View::addLocation($plugin->getPath() . '/views');

    /*
     * 配置页（views/config.blade.php）里的数据由视图自己调
     * kokuu_credit_config() 取实时值。
     *
     * 刻意**不**用 View::share：那是插件启动时的快照，管理员改完配置
     * 再刷新页面会看到旧值，得重启 PHP 才能更新。
     */

    Hook::addRoute(function ($router) {

        // ---- 配置保存 ----
        $router->post('plugin/kokuu-credit/settings', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            abort_if (! $user || $user->permission < User::ADMIN, 403);

            $cfg = kokuu_credit_config();

            // 管理平台密钥：留空表示不修改，避免后台回显时把明文覆盖掉
            $adminSecret = (string) $request->input('admin_secret', $cfg['admin_secret']);

            kokuu_credit_save_config([
                'admin_secret' => $adminSecret !== '' ? $adminSecret : $cfg['admin_secret'],
                'ttl'          => max(30, min(3600, (int) $request->input('ttl', 300))),
            ]);

            // ⚠️ 必须删 storage/options.php，否则配置永远不生效。
            @unlink(storage_path('options.php'));

            return response()->json(['code' => 0, 'message' => 'Saved.']);
        })->middleware(['web']);

        // ---- 流水列表（后台 AJAX） ----
        $router->get('plugin/kokuu-credit/api/ledger', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            abort_if (! $user || $user->permission < User::ADMIN, 403);

            kokuu_credit_ensure_tables();

            $limit = max(1, min(200, (int) $request->query('limit', 50)));
            $uid   = (int) $request->query('uid', 0);

            $query = DB::table('credit_ledger')->orderByDesc('id')->limit($limit);
            if ($uid > 0) {
                $query->where('uid', $uid);
            }

            $rows = $query->get();
            $nicknames = [];
            $uids = $rows->pluck('uid')->filter(fn ($v) => $v > 0)->unique()->all();
            if ($uids) {
                $nicknames = User::whereIn('uid', $uids)->pluck('nickname', 'uid')->toArray();
            }

            return response()->json([
                'code' => 0,
                'items' => $rows->map(function ($r) use ($nicknames) {
                    return [
                        'id'          => (int) $r->id,
                        'event_id'    => $r->event_id,
                        'uid'         => (int) $r->uid,
                        'nickname'    => $nicknames[$r->uid] ?? ($r->uid > 0 ? '已注销' : '—'),
                        'delta'       => (int) $r->delta,
                        'balance_after' => (int) $r->balance_after,
                        'source'      => $r->source,
                        'source_label' => kokuu_credit_source_label($r->source),
                        'note'        => $r->note,
                        'created_at'  => (string) $r->created_at,
                    ];
                })->all(),
                'stats' => kokuu_credit_stats(),
            ]);
        })->middleware(['web']);

        // ---- 用户搜索（调分面板用，管理员 AJAX） ----
        $router->get('plugin/kokuu-credit/api/users', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            abort_if(! $user || $user->permission < User::ADMIN, 403);

            $q = trim((string) $request->query('q', ''));

            /*
             * ⚠️ users 表**没有 username 列**（只有 uid / email / nickname / score），
             *    照抄别的项目的字段名会直接 Unknown column 500。
             *    全站统一用 nickname + email 搜。
             */
            $query = User::query()
                ->select('uid', 'nickname', 'email', 'score', 'permission')
                ->orderBy('uid')
                ->limit(20);

            if ($q !== '') {
                // 纯数字当 uid 精确查，否则按昵称/邮箱模糊匹配
                if (ctype_digit($q)) {
                    $query->where('uid', (int) $q);
                } else {
                    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                    $query->where(function ($sub) use ($like) {
                        $sub->where('nickname', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                }
            }

            return response()->json([
                'code'  => 0,
                'items' => $query->get()->map(function ($u) {
                    return [
                        'uid'        => (int) $u->uid,
                        'nickname'   => $u->nickname,
                        'email'      => $u->email,
                        'score'      => (int) $u->score,
                        'permission' => (int) $u->permission,
                    ];
                })->all(),
            ]);
        })->middleware(['web']);

        /*
         * ---- 管理员调分 ----
         *
         * 三种模式（mode）：
         *   add  加分，amount 为正数
         *   sub  扣分，amount 为正数（内部取负）
         *   set  直接把余额设为 amount（内部换算成差额）
         *
         * ★ 余额永远只经 kokuu_credit_ledger() 改，那里有 event_id 唯一索引
         *   作幂等键、且余额是数据库层 increment，不会并发丢更新。
         *   绝不直接 DB::table('users')->update(['score' => ...]) ——
         *   那样既没有流水，账本也永远不会自洽。
         */
        $router->post('plugin/kokuu-credit/api/adjust', function (\Illuminate\Http\Request $request) {
            $admin = $request->user();
            abort_if(! $admin || $admin->permission < User::ADMIN, 403);

            if (! function_exists('kokuu_credit_ledger')) {
                return response()->json(
                    ['code' => 1, 'message' => '账本函数缺失，请检查 kokuu-credit 是否完整'],
                    500
                );
            }
            kokuu_credit_ensure_tables();

            $uid   = (int) $request->input('uid', 0);
            $mode  = (string) $request->input('mode', '');
            $note  = trim((string) $request->input('note', ''));
            $raw   = $request->input('amount');

            if ($uid <= 0) {
                return response()->json(['code' => 1, 'message' => '请选择用户'], 422);
            }
            if (! in_array($mode, ['add', 'sub', 'set'], true)) {
                return response()->json(['code' => 1, 'message' => '未知的操作类型'], 422);
            }
            if ($note === '') {
                return response()->json(['code' => 1, 'message' => '请填写原因，这条会写进流水'], 422);
            }
            if (mb_strlen($note) > 100) {
                return response()->json(['code' => 1, 'message' => '原因请控制在 100 字以内'], 422);
            }
            // 空串与 0 必须区分：0 积分的调整是合法操作（清零），空串是漏填
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                return response()->json(['code' => 1, 'message' => '请填写数额'], 422);
            }
            $amount = (int) $raw;
            if ($mode !== 'set' && $amount <= 0) {
                return response()->json(['code' => 1, 'message' => '数额必须大于 0'], 422);
            }
            if ($mode === 'set' && $amount < 0) {
                return response()->json(['code' => 1, 'message' => '余额不能设为负数'], 422);
            }
            if (abs($amount) > 100000000) {
                return response()->json(['code' => 1, 'message' => '数额超出允许范围'], 422);
            }

            $target = User::find($uid);
            if (! $target) {
                return response()->json(['code' => 1, 'message' => '用户不存在'], 422);
            }

            $before = (int) DB::table('users')->where('uid', $uid)->value('score');

            if ($mode === 'set') {
                $delta = $amount - $before;
            } else {
                $delta = $mode === 'add' ? $amount : -$amount;
            }

            if ($delta === 0) {
                return response()->json([
                    'code'    => 0,
                    'message' => '余额本来就是 ' . $before . '，没有改动',
                    'score'   => $before,
                    'skipped' => true,
                ]);
            }

            $after = $before + $delta;
            if ($after < 0) {
                return response()->json([
                    'code'    => 1,
                    'message' => '扣分后余额会变成负数（当前 ' . $before . '），已拒绝',
                ], 422);
            }

            /*
             * ⚠️ 管理员手动调分**不受底仓 min_keep 约束** —— 底仓是给
             *    「用户自己兑换/抽奖」设的自保线，管理员是站点所有者，
             *    需要能纠错（发错要收回、刷分要清零）。
             *    但负余额仍然拒绝（见上面的 $after < 0）。
             */

            /*
             * 幂等键：同一管理员 + 同一目标 + 同一 nonce 只生效一次。
             * nonce 由前端每次点击生成，所以正常点两次就是两次独立操作
             * （符合直觉），而网络重试/双击回放同一个 nonce 不会重复生效。
             */
            $nonce = trim((string) $request->input('nonce', ''));
            if ($nonce === '') {
                $nonce = bin2hex(random_bytes(8));
            }
            $eventId = 'manual:' . $admin->uid . ':' . $uid . ':' . substr($nonce, 0, 32);

            $ok = kokuu_credit_ledger(
                $uid,
                $delta,
                'manual',
                $eventId,
                'admin:' . $admin->uid,
                $note
            );

            if (! $ok) {
                return response()->json([
                    'code'    => 1,
                    'message' => '这个操作已经执行过了（幂等命中），没有重复加分',
                ], 422);
            }

            $real = (int) DB::table('users')->where('uid', $uid)->value('score');

            return response()->json([
                'code'    => 0,
                'message' => '已' . ($delta > 0 ? '增加' : '扣减') . ' ' . abs($delta) . ' 积分',
                'uid'     => $uid,
                'before'  => $before,
                'delta'   => $delta,
                'score'   => $real,
                'event_id' => $eventId,
            ]);
        })->middleware(['web']);

        // ---- 签名自检（后台"测试连通"用） ----
        /*
         * ==================================================================
         *  管理平台（KokuuPanel）用的签名接口
         *
         *  平台是「结算中枢」：账本仍然只有这里一份（users.score +
         *  credit_ledger），平台通过这三个接口读写，不另建账本。
         *
         *  为什么不能直接用上面的 /api/users、/api/ledger、/api/adjust：
         *  它们是 `web` 中间件 + 管理员**会话**，只能被浏览器里已登录的
         *  页面调用；平台是服务端到服务端，没有会话。所以这三个是同一套
         *  业务逻辑的**签名版**，鉴权换成 kokuu_credit_admin_verify。
         *
         *  平台按 uid 寻址（它存了 bs_uid），不需要玩家邮箱。
         * ==================================================================
         */

        // ---- 用户列表 / 搜索 ----
        $router->post('plugin/kokuu-credit/api/admin/users', function (\Illuminate\Http\Request $request) {
            $body   = $request->all();
            $kw     = trim((string) ($body['kw'] ?? ''));
            $page   = max(1, (int) ($body['page'] ?? 1));
            $size   = max(1, min(100, (int) ($body['size'] ?? 20)));

            $error = kokuu_credit_admin_verify($body, [
                'kw' => $kw, 'page' => $page, 'size' => $size,
            ]);
            if ($error !== null) {
                return response()->json(['code' => 1, 'message' => $error], 403);
            }

            $query = User::query();
            if ($kw !== '') {
                // 关键字可能是 uid（数字）、昵称或邮箱。
                // 括号包住这一组 or，否则会和外层的其它条件串成错误的语义。
                $query->where(function ($q) use ($kw) {
                    if (ctype_digit($kw)) {
                        $q->orWhere('uid', (int) $kw);
                    }
                    $q->orWhere('nickname', 'like', '%' . $kw . '%')
                      ->orWhere('email', 'like', '%' . $kw . '%');
                });
            }

            $total = (clone $query)->count();
            $rows  = $query->orderBy('uid')
                ->skip(($page - 1) * $size)
                ->take($size)
                ->get(['uid', 'nickname', 'email', 'score']);

            return response()->json([
                'code'  => 0,
                'total' => $total,
                'items' => $rows->map(function ($u) {
                    return [
                        'uid'      => (int) $u->uid,
                        'nickname' => (string) $u->nickname,
                        'score'    => (int) $u->score,
                    ];
                })->values(),
            ]);
        })->middleware(['api', 'throttle:300,1']);

        // ---- 流水查询 ----
        $router->post('plugin/kokuu-credit/api/admin/ledger', function (\Illuminate\Http\Request $request) {
            $body   = $request->all();
            $uid    = (int) ($body['uid'] ?? 0);
            $source = trim((string) ($body['source'] ?? ''));
            $limit  = max(1, min(200, (int) ($body['limit'] ?? 50)));

            $error = kokuu_credit_admin_verify($body, [
                'uid' => $uid, 'source' => $source, 'limit' => $limit,
            ]);
            if ($error !== null) {
                return response()->json(['code' => 1, 'message' => $error], 403);
            }

            kokuu_credit_ensure_tables();

            $query = DB::table('credit_ledger')->orderByDesc('id')->limit($limit);
            if ($uid > 0) {
                $query->where('uid', $uid);
            }
            if ($source !== '') {
                $query->where('source', $source);
            }
            $rows = $query->get();

            $uids = $rows->pluck('uid')->filter(function ($v) {
                return $v > 0;
            })->unique()->all();
            $nicknames = $uids
                ? User::whereIn('uid', $uids)->pluck('nickname', 'uid')->toArray()
                : [];

            return response()->json([
                'code'  => 0,
                'total' => $rows->count(),
                'items' => $rows->map(function ($r) use ($nicknames) {
                    return [
                        'id'            => (int) $r->id,
                        'uid'           => (int) $r->uid,
                        'nickname'      => $nicknames[(int) $r->uid] ?? null,
                        'delta'         => (int) $r->delta,
                        'balance_after' => (int) $r->balance_after,
                        'source'        => (string) $r->source,
                        'ref'           => (string) ($r->ref ?? ''),
                        'note'          => (string) ($r->note ?? ''),
                        'event_id'      => (string) $r->event_id,
                        // 返回**秒级时间戳**而不是 MySQL 的 datetime：
                        // 那个字符串没有时区标记，平台在别的时区解析会整体偏移。
                        'created_at'    => $r->created_at ? strtotime((string) $r->created_at) : 0,
                    ];
                })->values(),
            ]);
        })->middleware(['api', 'throttle:300,1']);

        // ---- 改余额（含扣减 / 清零） ----
        $router->post('plugin/kokuu-credit/api/admin/adjust', function (\Illuminate\Http\Request $request) {
            $body     = $request->all();
            $uid      = (int) ($body['uid'] ?? 0);
            $delta    = (int) ($body['delta'] ?? 0);
            $source   = trim((string) ($body['source'] ?? 'admin'));
            $eventId  = trim((string) ($body['event_id'] ?? ''));
            $note     = trim((string) ($body['note'] ?? ''));
            $operator = trim((string) ($body['operator'] ?? ''));

            $error = kokuu_credit_admin_verify($body, [
                'event_id' => $eventId, 'uid' => $uid, 'delta' => $delta, 'source' => $source,
            ]);
            if ($error !== null) {
                return response()->json(['code' => 1, 'message' => $error], 403);
            }

            if ($uid <= 0) {
                return response()->json(['code' => 1, 'message' => '缺少 uid'], 422);
            }
            if ($delta === 0) {
                // 不接受 0：一次「加 0 积分」在流水里毫无意义，只会制造噪音。
                return response()->json(['code' => 1, 'message' => 'delta 不能为 0'], 422);
            }
            if ($eventId === '') {
                return response()->json(['code' => 1, 'message' => '缺少 event_id（幂等键）'], 422);
            }
            if (mb_strlen($eventId) > 80) {
                // credit_ledger.event_id 是 varchar(80)，超了会被数据库截断 → 幂等失效
                return response()->json(['code' => 1, 'message' => 'event_id 超过 80 字符'], 422);
            }

            kokuu_credit_ensure_tables();

            $target = User::find($uid);
            if (! $target) {
                return response()->json(['code' => 1, 'message' => '用户不存在'], 422);
            }

            $before = (int) DB::table('users')->where('uid', $uid)->value('score');
            if ($before + $delta < 0) {
                return response()->json([
                    'code'    => 1,
                    'message' => '扣减后余额会变成负数（当前 ' . $before . '，本次 ' . $delta . '），已拒绝',
                ], 422);
            }

            $ok = kokuu_credit_ledger(
                $uid,
                $delta,
                $source !== '' ? $source : 'admin',
                $eventId,
                $operator !== '' ? 'admin:' . $operator : 'admin',
                $note !== '' ? mb_substr($note, 0, 190) : null
            );

            $balance = (int) DB::table('users')->where('uid', $uid)->value('score');
            $ledgerId = (int) DB::table('credit_ledger')->where('event_id', $eventId)->value('id');

            if (! $ok) {
                /*
                 * 幂等命中 —— 这是**成功语义**，不是错误。
                 *
                 * 幂等键的用途就是让调用方在没收到响应时重试；重试理应拿到成功，
                 * 而不是一个需要特殊处理的分支。回 code:0 + duplicate:true，
                 * 平台据此告诉管理员「这次没有重复入账」。
                 * （注意与上面 /api/adjust 不同：那个是浏览器里的双击，
                 *   报错更符合预期；这里是服务端重试，报错会让重试逻辑变复杂。）
                 */
                return response()->json([
                    'code'      => 0,
                    'duplicate' => true,
                    'balance'   => $balance,
                    'ledger_id' => $ledgerId,
                    'message'   => '这个 event_id 已经处理过，本次没有重复入账',
                ]);
            }

            return response()->json([
                'code'      => 0,
                'duplicate' => false,
                'balance'   => $balance,
                'ledger_id' => $ledgerId,
                'before'    => $before,
                'delta'     => $delta,
            ]);
        })->middleware(['api', 'throttle:300,1']);

        // ---- 签名自检（后台"测试连通"用） ----


    });

    // ---- 页面样式 ----
    Hook::addStyleFileToPage($plugin->assets('credit.css'), [
        'admin', 'admin/*',
        'plugin/kokuu-credit/*',
    ]);

    Hook::addScriptFileToPage($plugin->assets('credit.js'), [
        'admin', 'admin/*',
        'plugin/kokuu-credit/*',
    ]);

    /*
     * 仪表盘专用样式。
     *
     * 单独一个文件而不是并进 credit.css：这套 apple 风格样式
     * 有 500 多行（含响应式与深色模式），而 credit.css 还要服务
     * 配置页，混在一起两边都不好维护。
     *
     * ⚠️ `admin` 这一项是**精确匹配 /admin 本身**，
     *   `admin/*` 才能覆盖 /admin/users 这类子页面，
     *   两个都要写 —— 仪表盘正好是前者。
     *
     * ⚠️ `user` 同理：精确匹配 /user 本身（就是用户中心仪表盘），
     *   `user/*` 覆盖 /user/profile 等子页。
     *   **不能漏掉 user 侧**——用户中心和后台仪表盘是两套独立 widget，
     *   后台接管了不代表用户中心也跟着变。
     */
    Hook::addStyleFileToPage($plugin->assets('dash.css'), [
        'admin', 'admin/*',
        'user', 'user/*',
    ]);

    /*
     * ---- 签到按钮脚本 ----
     *
     * ⚠️ 单独一个文件、且**注册在仪表盘路由上**（与 dash.css 同范围）。
     *   不能并进 credit.js —— 那个只注册在 `admin/*` 与
     *   `plugin/kokuu-credit/*`，而签到按钮恰好长在**用户中心**
     *   `/user` 上。并进去的话普通用户永远看不到按钮的交互。
     *
     * 为什么需要它：内核签到 UI 是挂在原生 usage widget 上的 React 组件，
     * 我们接管 grid 后该 widget 被替换掉，按钮随之消失。详见 sign.js 头注。
     */
    Hook::addScriptFileToPage($plugin->assets('sign.js'), [
        'admin', 'admin/*',
        'user', 'user/*',
    ]);

    /*
     * ---- 流水页补 apple 作用域 ----
     *
     * 流水页 URL 是 `plugin/kokuu-credit/ledger`，而 kokuu-ui 的
     * rebuild.js 只注入 `user` / `user/*` / `admin` / `admin/*` /
     * `skinlib` / `skinlib/*` 六个前缀 —— **不含 plugin/**。
     *
     * 后果（实测）：流水页 body 上没有 `kokuu-apple` 类，
     * apple.css 里 `body.kokuu-apple xxx` 的作用域规则全部不生效，
     * 页面退回内核默认的紫渐变背景 + 虚空深色表格，
     * 和隔壁仪表盘的 Apple 扁平化完全不是一套东西。
     *
     * → 这里引用 kokuu-ui 的 apple.css（**不改 kokuu-ui 本身**），
     *   再用一小段脚本把 `kokuu-apple` 打到 body 上。
     *
     * ⚠️ 只加类名，不复制 rebuild.js 的任何行为（侧栏折叠、
     *   stagger 动画那些）—— 那些是 kokuu-ui 的职责，
     *   复制一份会两边状态打架（都往 localStorage 写同一个 key）。
     */
    Hook::addStyleFileToPage('/plugins/kokuu-appearance/assets/apple.css', [
        'plugin/kokuu-credit/*',
    ]);

    Hook::addScriptFileToPage($plugin->assets('apple-scope.js'), [
        'plugin/kokuu-credit/*',
    ]);
    /*
     * ---- 侧边栏入口 ----
     *
     * 挂到「用户」菜单下让管理员能直接进流水页。
     * 事件给的是整个 $menu 数组（含 user/admin/explore 三个键）的引用，
     * 监听器第一个参数是事件对象，所以必须写 $event->menu['user'][]。
     */
    /*
     * 积分流水是**管理功能**，只挂到后台（admin）菜单，不再出现在用户中心。
     *
     * 历史问题：原先挂在 ConfigureUserMenu（用户侧菜单），
     * 普通用户也能看到「积分流水」入口 —— 但该路由本身要求 ADMIN，
     * 点了就是 403。用户反馈「用户的后台页面怎么还有积分流水这种东西」。
     * 现在改挂 ConfigureAdminMenu，普通用户完全不可见，管理员在后台可查。
     */
    \Illuminate\Support\Facades\Event::listen(\App\Events\ConfigureAdminMenu::class, function ($event) {
        $event->menu['admin'][] = [
            'title' => '积分流水',
            'link' => 'plugin/kokuu-credit/ledger',
            'icon' => 'fa-coins',
        ];
    });

    // ---- 流水页面 ----
    Hook::addRoute(function ($router) use ($plugin) {
        // ⚠️ 内层闭包**必须**再写一次 use ($plugin)。
        //   外层的 use ($plugin) 只让 $plugin 对**外层闭包**可见，
        //   内层 function () {} 是另一个作用域，看不到外层的 use 变量。
        //   漏掉时的表现是 500：
        //     Undefined variable $plugin at bootstrap.php:996
        //   （PHP 只在真正执行到这一行时才抛，所以路由注册阶段完全正常，
        //     只有管理员点进流水页才炸 —— 很容易误判成视图模板问题。）
        $router->get('plugin/kokuu-credit/ledger', function (\Illuminate\Http\Request $request) use ($plugin) {
            $user = $request->user();
            abort_if (! $user || $user->permission < User::ADMIN, 403);

            $cfg = kokuu_credit_config();
            kokuu_credit_ensure_tables();

            return view()->file($plugin->getPath() . '/views/ledger.twig', [
                'cfg' => $cfg,
                'stats' => kokuu_credit_stats(),
                // 同一份标签表，别再抄 —— 见 kokuu_credit_source_label() 的注释
                'sourceLabels' => kokuu_credit_source_labels(),
            ]);
        })->middleware(['web']);
    });

    /*
     * ---- 后台仪表盘接管 ----
     *
     * 为什么用 `Filter::apply('grid:admin.index', ...)` 而不是直接改 core：
     *   AdminController::index() 里写的是
     *     $grid = $filter->apply('grid:admin.index', $grid);
     *   这个钩子拿到的是**最终** $grid，我们可以整个替换掉。
     *   于是 core 一个字都不用动，插件卸载后仪表盘自动恢复原样 ——
     *   这也是不该去patch vendor 的原因。
     *
     * 替换掉什么：原grid 是
     *   [ [ usage, notification ], [ chart ] ]
     * 其中 usage 里有「角色数量」和「存储空间」两块 —— 这个站已经不做
     * 皮肤托管了，只用来验证账号和记积分，这两块永远是没有意义的死数据。
     * 现在只留两个容器：积分 + 社区互联。
     *
     * ⚠️ layout 的列数必须和 widgets 里的行**完全一致**，
     *   shared/grid.twig 是 `grid.layout[行下标][列下标]` 取的，
     *   对不上会直接渲染错位。
     *
     * ⚠️ 注册过滤器用 `Filter::add()`，**不是** `Filter::apply()` ——
     *   apply() 是「执行」钩子的方法，add() 才是「注册」。
     *   回调签名只有 `function ($grid)` 一个参数：
     *   AdminController 调的是 `$filter->apply('grid:admin.index', $grid)`，
     *   没传 $args，所以第二个参数不存在，写了也不会被调用。
     *   返回值直接替换整个 $grid。
     *
     * ⚠️ 取实例必须用 `app(Blessing\Filter::class)`，**不能**用 `app('filter')` ——
     *   容器里没有 'filter' 这个字符串绑定，实测直接抛
     *   `Target class [filter] does not exist`。
     *   而外层没有 catch，这个异常发生在 bootstrap 阶段，
     *   表现是「插件看着启用了，仪表盘却毫无变化」—— 不报错，只是静默失效。
     *
     * 视图名解析：命名空间是 **manifest 里`namespace` 字段的原值**，
     * 即 `KokuuStudio\KokuuCredit` —— **反斜杠**，不是短名 `kokuu-credit`。
     * PluginManager::loadViewsAndTranslations() 做的是
     *   $view->addNamespace($plugin->namespace, $path.'/views');
     * 在 PHP 单引号字符串里写要双写反斜杠：`'KokuuStudio\\KokuuCredit::dashboard.credit'`。
     *
     * ⚠️ 用短名会直接 500，且报错只有一句
     *   `Template "kokuu-credit::dashboard.credit" is not defined in "shared.grid" at line 7`
     *   —— 不会提示正确名字。只能靠
     *   `$app->make('view')->getFinder()->getHints()` 打出来确认。
     * ⚠️⚠️ 数据必须用 `View::share()` 传进视图，**不能**在 twig 里写
     *   `{{ kokuu_credit_dashboard_data(...) }}` ——
     *   Twig 沙箱只放行注册过的函数，自定义 PHP 函数一律报
     *     Unknown "kokuu_credit_dashboard_data" function
     *   内核自带的是 auth_user / auth_check / auth_guest / auth_guard / url / trans，
     *   **没有 `auth()`**（写了报 Unknown "auth" function）。
     *   现有 ledger.twig 也是全靠 Controller 传变量，自己不调任何自定义函数。
     *
     *   share 的时机安全：AdminController 是先 apply() 再渲染 admin.index，
     *   此刻视图还没开始输出。
     */
    app(\Blessing\Filter::class)->add('grid:admin.index', function ($grid) {
        // 先算好数据挂到视图上，两个 widget 各自读，不重复查库
        View::share('kcDash', kokuu_credit_dashboard_data(auth()->user()));

        return [
            'layout' => [
                ['md-7', 'md-5'],
            ],
            'widgets' => [
                // ⚠️⚠️ 三层嵌套，别少写一层！
                //   shared/grid.twig 是：
                //     for row in grid.widgets        <- 行
                //       for col in row              <- 列
                //         for widget in col        <- 挂在列里的 widget
                //   core 原值[[usage, notification], [chart]] 就是这个形状：
                //     行0 = [ 列0=[usage], 列1=[notification] ]
                //     行1 = [ 列0=[chart] ]
                //   我最初写成 [[w1, w2]]（行0 里只有 1 列，列里塞 2 个 widget），
                //   结果第二个 widget 挤在第一列里上下堆叠，
                //   截图上表现为「卡片全纵向排开」，而layout 的 md-5 根本没被消费。
                [
                    ['KokuuStudio\\KokuuCredit::dashboard.credit'],
                ],
            ],
        ];
    });

    /*
     * ---- 用户中心仪表盘接管 ----
     *
     * 为什么必须单独注册一次：后台 `/admin` 和用户中心 `/user` 是
     * **两套完全独立的 grid**（AdminController::index 与 UserController::index
     * 各调一次自己的 filter），钩子名也不同：
     *   后台  → grid:admin.index
     *   用户  → grid:user.index
     * 只接后台的话，用户中心仍是老样子（深色卡片 + 荧光色块），
     * 表现为「管理面板有新UI，普通用户那边没有」。
     *
     * ⚠️ kokuu-ui/apple.css 的作用域是 `body.kokuu-apple.*`，
     *   而这个类是 rebuild.js 在**后台页面**才加的
     *   （实测 /user 的 body 上没有 kokuu-apple），
     *   所以用户中心这边apple 变量要自己兜底，
     *   dash.css 里所有 var() 都带了fallback 值，正是为此。
     *
     * ⚠️ 邮箱验证 widget 不能丢：SSO 账号虽然建号时就 confirmed=1，
     *   但老账号可能有漏网的，留着内核自带的提醒更稳妥。
     *   它放在左列积分卡**上方**，只占一行、不抢版面。
     */
    app(\Blessing\Filter::class)->add('grid:user.index', function ($grid) {
        // ⚠️ share 键名用 kcDashUser 而不是复用 kcDash：
        //   两个钩子可能先后作用于同一个请求，
        //   共用一个键会互相覆盖。View::share 是全局的，不隔离。
        View::share('kcDashUser', kokuu_credit_dashboard_data(auth()->user()));

        /*
         * ⚠️⚠️⚠️ 必须**在传入的 $grid 上增量改**，不要整个 return 一个新数组。
         *
         * 原因：`grid:user.index` 上挂了**不止一个** Filter 回调 ——
         *   · 本插件（接管布局）
         *   · kokuu-forum（注入社区卡 user.widgets.dashboard.forum，
         *     并移除内核的 announcement）
         * 多个回调是**链式**的：前一个的返回值作为后一个的输入。
         *
         * 我第一版直接 return 了一个全新数组，结果把 kokuu-forum
         * 后续要用的上下文整个换掉了 —— 社区卡被塞到别的列、
         * 左边还留一大片空白（`class="col-"`，因为 layout 只有一行
         * 而 widgets 给了两行，grid.twig 取 layout[1] 越界）。
         *
         * → 正确做法：沿用内核原布局，只**换掉**左列的 usage widget
         *   （角色数量/存储空间）成我们的积分卡，右列原样留给
         *   kokuu-forum 的社区卡。这样两个插件互不干扰。
         */
        if (! isset($grid['widgets'][0][0]) || ! is_array($grid['widgets'][0][0])) {
            $grid['widgets'][0][0] = [];
        }

        // 左列：邮箱验证保留，usage 换成积分卡。
        // 用 array_filter + array_values 压平——
        // grid.twig 按下标 foreach，中间挖元素会留下不连续下标。
        $col = array_values(array_filter(
            (array) $grid['widgets'][0][0],
            fn ($w) => $w !== 'user.widgets.dashboard.usage'
        ));
        array_unshift($col, 'KokuuStudio\\KokuuCredit::user.credit');
        $grid['widgets'][0][0] = $col;

        /*
         * ⚠️ 右列**一个字都不动**。
         *
         * 曾经在这里插一张自己的「社区互联」卡，结果是纯多余：
         * 用户明确说过「用户中心的仪表盘只看两个东西，一个是这个积分，
         * 一个是现有的社区」——「现有的社区」指的就是 kokuu-forum
         * 那张社区 feed，再加一张互联卡就是第三个东西，
         * 而且因为回调是链式的、顺序不确定，它会被顶到 feed 下面
         * （实测截图里只在右下角露出一角），纯粹占位。
         *
         * 互联该有的信息（怎么加分、论坛在哪）已经并进左列积分卡底部，
         * 见 views/user/credit.twig。
         */
        return $grid;
    });
};


