<?php

use App\Models\User;
use App\Services\Hook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Kokuu\Lottery\Core\LotteryService;
use Kokuu\Lottery\Host\BsLotteryConfig;
use Kokuu\Lottery\Host\BsScoreAccount;
use Kokuu\Lottery\Host\CreditLedgerSink;
use Kokuu\Lottery\Host\DbDrawLogRepository;
use Kokuu\Lottery\Host\DbAssetAccount;
use Kokuu\Lottery\Host\DbPityRepository;
use Kokuu\Lottery\Host\DbPrizeProvider;
use Kokuu\Lottery\Host\DbWalletRepository;
use Kokuu\Lottery\Host\LaravelTransaction;
use Kokuu\Lottery\Host\Schema_;

/*
 * KokuuSkin 抽奖（kokuu-lottery）
 *
 * 从 kokuu-coupon 中拆出的独立插件。
 *
 * 【架构】分两层：
 *   src/Core/   可移植领域核心，不 import 任何宿主命名空间
 *   src/Host/   BlessingSkin 适配层，唯一知道 Laravel 存在的地方
 *
 * 这样抽奖逻辑可脱离皮肤站，在任意 PHP 项目复用（换框架只需重写 Host）。
 *
 * 【兑换码已移除】原 kokuu-coupon 的兑换码功能已删除，
 *   本插件只保留抽奖。奖品仍可用「用码获得的次数」来抽（via=code），
 *   但发码入口已不存在——如需发次数，请用积分购买或后续另行集成。
 *
 * 【数据表】lottery_prizes / lottery_draws / lottery_wallets
 *   结构与原 kokuu-coupon 完全一致，数据可直接沿用，无需迁移。
 */

/* =========================================================
 * 辅助：构建服务实例
 * ========================================================= */
if (! function_exists('kokuu_lottery_service')) {
    function kokuu_lottery_service(): LotteryService
    {
        static $svc = null;
        if ($svc !== null) {
            return $svc;
        }

        $svc = new LotteryService(
            new DbPrizeProvider(),
            new DbWalletRepository(),
            new DbDrawLogRepository(),
            new BsScoreAccount(),
            new DbAssetAccount(),      // v2：钻石/绿宝石资产
            new DbPityRepository(),    // v2：保底计数
            new LaravelTransaction(),
            new CreditLedgerSink(),
            new BsLotteryConfig()
        );

        return $svc;
    }
}

/* =========================================================
 * 辅助：配置读写（供配置页使用）
 * ========================================================= */
if (! function_exists('kokuu_lottery_config_read')) {
    function kokuu_lottery_config_read(): array
    {
        $c = app('options');
        $pick = function (string $k, $def) use ($c) {
            $v = $c->get('kokuu_lottery_'.$k);
            if ($v === null || $v === '') {
                $v = $c->get('kokuu_coupon_'.$k);  // 平滑迁移
            }

            return ($v === null || $v === '') ? $def : $v;
        };

        return [
            'draw_cost' => (int) $pick('draw_cost', 100),
            'draw_cooldown' => (int) $pick('draw_cooldown', 0),
            'daily_draw_limit' => (int) $pick('daily_draw_limit', 0),
            'min_keep' => (int) $pick('min_keep', 0),
            'pity_purple' => (int) $pick('pity_purple', 10),
            'pity_gold' => (int) $pick('pity_gold', 80),
            'notice' => (string) $pick('notice', ''),
        ];
    }
}

if (! function_exists('kokuu_lottery_config_write')) {
    function kokuu_lottery_config_write(array $p): void
    {
        $c = app('options');
        foreach (['draw_cost', 'draw_cooldown', 'daily_draw_limit', 'min_keep', 'pity_purple', 'pity_gold', 'notice'] as $k) {
            if (array_key_exists($k, $p)) {
                $c->set('kokuu_lottery_'.$k, $p[$k]);
            }
        }

        // 必须删缓存文件，否则配置不生效（Option 构造函数优先读 storage/options.php）
        @unlink(storage_path('options.php'));
    }
}

return function ($plugin) {
    // 视图命名空间
    View::addLocation($plugin->getPath().'/views');

    /*
     * ⚠️ 资源不在 bootstrap 里 Hook 注入（2026-11-03 修正）
     *
     * 原因：模板 before_foot 里也写了一遍 <link>/<script>，导致
     * lottery.css / lottery.js 各被加载两次 —— 其中一次不带 ?v= 版本号，
     * 浏览器就用了旧缓存（改了半天 JS 用户看到的还是老行为）。
     *
     * 现在统一由模板 before_foot 注入，因为顺序可控：
     * 必须先内联 window.KG_CSRF，再加载 lottery.js。
     * Hook 注入无法保证这个顺序。
     */

    /* =========================================================
     * 侧边栏菜单：抽奖入口
     * ========================================================= */
    \Illuminate\Support\Facades\Event::listen(
        \App\Events\ConfigureUserMenu::class,
        function ($event) {
            $event->menu['user'][] = [
                'title' => '抽奖',
                'link' => 'plugin/kokuu-lottery',
                'icon' => 'fa-dice',
            ];
        }
    );

    /* =========================================================
     * 路由
     * ========================================================= */
    Hook::addRoute(function ($router) use ($plugin) {
        // ---- 抽奖页面 ----
        $router->get('plugin/kokuu-lottery', function (Request $request) use ($plugin) {
            $user = $request->user();
            abort_if(! $user, 403);

            $svc = kokuu_lottery_service();
            $cfg = kokuu_lottery_config_read();
            $uid = (int) $user->uid;

            $cost = (int) $cfg['draw_cost'];
            $keep = (int) $cfg['min_keep'];
            // 金粒 = 皮肤站积分
            $gold = (int) DB::table('users')->where('uid', $uid)->value('score');
            $spendable = max(0, $gold - $keep);

            $assets = $svc->assets($uid);
            $pity = $svc->pityState($uid);

            View::share('lottery', [
                'cost' => $cost,
                'keep' => $keep,
                'gold' => $gold,
                'spendable' => $spendable,
                'diamond' => (int) ($assets['diamond'] ?? 0),
                'emerald' => (int) ($assets['emerald'] ?? 0),
                'canDraw' => $spendable >= $cost,
                'canTen' => $spendable >= $cost * 10,
                'drawUrl' => url('plugin/kokuu-lottery/draw'),
                'odds' => $svc->oddsTable(),
                'pityPurple' => (int) $cfg['pity_purple'],
                'pityGold' => (int) $cfg['pity_gold'],
                'sincePurple' => (int) $pity['since_purple'],
                'sinceGold' => (int) $pity['since_gold'],
                'totalDraws' => (int) $pity['total_draws'],
            ]);

            Schema_::ensure();
            $myDraws = DB::table('lottery_draws')
                ->where('uid', $uid)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['prize_label', 'amount', 'rarity', 'asset', 'created_at']);
            View::share('myDraws', $myDraws);

            return view('user.lottery.index');
        })->middleware(['web', 'auth']);

        // ---- 执行抽卡（支持单抽 times=1 / 十连 times=10）----
        $router->post('plugin/kokuu-lottery/draw', function (Request $request) {
            $user = $request->user();
            abort_if(! $user, 403);

            $times = (int) $request->input('times', 1);
            $times = ($times >= 10) ? 10 : 1;

            $svc = kokuu_lottery_service();
            $result = $svc->drawMany((int) $user->uid, $times, (string) $request->ip());

            return response()->json($result->toArray());
        })->middleware(['web', 'auth']);

        // ---- 最近抽奖记录 ----
        $router->get('plugin/kokuu-lottery/history', function (Request $request) {
            $user = $request->user();
            abort_if(! $user, 403);

            Schema_::ensure();
            $rows = DB::table('lottery_draws')
                ->where('uid', (int) $user->uid)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['prize_label', 'amount', 'via', 'created_at']);

            return response()->json(['code' => 0, 'data' => $rows]);
        })->middleware(['web', 'auth']);

        // ---- 概率表（前端展示）----
        $router->get('plugin/kokuu-lottery/odds', function () {
            return response()->json([
                'code' => 0,
                'data' => kokuu_lottery_service()->oddsTable(),
            ]);
        })->middleware(['web', 'auth']);

        // ---- 管理：奖品 CRUD ----
        $router->post('plugin/kokuu-lottery/prize', function (Request $request) {
            $user = $request->user();
            abort_if(! $user || $user->permission < User::ADMIN, 403);

            Schema_::ensure();
            $action = (string) $request->input('action', '');

            switch ($action) {
                case 'create':
                    DB::table('lottery_prizes')->insert([
                        'label' => mb_substr((string) $request->input('label', '奖品'), 0, 80),
                        'amount' => max(0, (int) $request->input('amount', 0)),
                        'weight' => max(0, (int) $request->input('weight', 1)),
                        'sort' => (int) $request->input('sort', 0),
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return response()->json(['code' => 0, 'message' => '已创建奖品']);

                case 'update':
                    $id = (int) $request->input('id', 0);
                    DB::table('lottery_prizes')->where('id', $id)->update([
                        'label' => mb_substr((string) $request->input('label', ''), 0, 80),
                        'amount' => max(0, (int) $request->input('amount', 0)),
                        'weight' => max(0, (int) $request->input('weight', 1)),
                        'enabled' => (bool) $request->input('enabled', true),
                        'updated_at' => now(),
                    ]);

                    return response()->json(['code' => 0, 'message' => '已更新']);

                case 'delete':
                    DB::table('lottery_prizes')->where('id', (int) $request->input('id', 0))->delete();

                    return response()->json(['code' => 0, 'message' => '已删除']);

                default:
                    return response()->json(['code' => 1, 'message' => '未知操作'], 422);
            }
        })->middleware(['web', 'auth', 'role:admin']);

        // ---- 管理：配置保存 ----
        $router->post('plugin/kokuu-lottery/config', function (Request $request) {
            $user = $request->user();
            abort_if(! $user || $user->permission < User::ADMIN, 403);

            kokuu_lottery_config_write([
                'draw_cost' => (int) $request->input('draw_cost', 50),
                'draw_cooldown' => (int) $request->input('draw_cooldown', 3),
                'daily_draw_limit' => (int) $request->input('daily_draw_limit', 50),
                'notice' => (string) $request->input('notice', ''),
            ]);

            return response()->json(['code' => 0, 'message' => '已保存']);
        })->middleware(['web', 'auth', 'role:admin']);

        // ---- 管理：统计 ----
        $router->get('plugin/kokuu-lottery/stats', function (Request $request) {
            $user = $request->user();
            abort_if(! $user || $user->permission < User::ADMIN, 403);

            Schema_::ensure();

            return response()->json([
                'code' => 0,
                'data' => [
                    'total_draws' => (int) DB::table('lottery_draws')->count(),
                    'total_amount' => (int) DB::table('lottery_draws')->sum('amount'),
                    'today_draws' => (int) DB::table('lottery_draws')
                        ->where('created_at', '>=', now()->startOfDay())->count(),
                    'prizes' => DB::table('lottery_prizes')->orderBy('sort')->get(),
                ],
            ]);
        })->middleware(['web', 'auth', 'role:admin']);
    });
};

