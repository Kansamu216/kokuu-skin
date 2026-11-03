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
            'draw_cost' => (int) $pick('draw_cost', 50),
            'draw_cooldown' => (int) $pick('draw_cooldown', 3),
            'daily_draw_limit' => (int) $pick('daily_draw_limit', 50),
            'notice' => (string) $pick('notice', ''),
        ];
    }
}

if (! function_exists('kokuu_lottery_config_write')) {
    function kokuu_lottery_config_write(array $p): void
    {
        $c = app('options');
        foreach (['draw_cost', 'draw_cooldown', 'daily_draw_limit', 'notice'] as $k) {
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

    // 样式与脚本（仅抽奖页）
    Hook::addStyleFileToPage($plugin->assets('lottery.css'), [
        'plugin/kokuu-lottery',
        'plugin/kokuu-lottery/*',
    ]);
    Hook::addScriptFileToPage($plugin->assets('lottery.js'), [
        'plugin/kokuu-lottery',
        'plugin/kokuu-lottery/*',
    ]);

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

            $walletDraws = $svc->walletBalance($uid);
            $cost = (int) $cfg['draw_cost'];
            $keep = (new BsLotteryConfig())->minKeep();
            $balance = (int) DB::table('users')->where('uid', $uid)->value('score');
            $spendable = $balance - $keep;
            $cooldown = $svc->cooldownLeft($uid);

            // 能否抽：有次数 或（开放积分抽奖且可用积分够）
            $canDraw = $walletDraws > 0 || ($cost > 0 && $spendable >= $cost);

            Schema_::ensure();
            $myDraws = DB::table('lottery_draws')
                ->where('uid', $uid)
                ->orderByDesc('id')
                ->limit(8)
                ->get(['prize_label', 'amount', 'via', 'created_at']);

            // 模板用 d.* 访问（保留原 kokuu-coupon 模板契约）
            View::share('lottery', [
                'draws' => $walletDraws,
                'spendable' => max(0, $spendable),
                'cost' => $cost,
                'keep' => $keep,
                'cooldown' => $cooldown,
                'canDraw' => $canDraw,
                'drawUrl' => url('plugin/kokuu-lottery/draw'),
                'odds' => $svc->oddsTable(),
            ]);
            View::share('myDraws', $myDraws);

            return view('user.lottery.index');
        })->middleware(['web', 'auth']);

        // ---- 执行抽奖 ----
        $router->post('plugin/kokuu-lottery/draw', function (Request $request) {
            $user = $request->user();
            abort_if(! $user, 403);

            $svc = kokuu_lottery_service();
            $result = $svc->draw((int) $user->uid, (string) $request->ip());

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

