<?php

use App\Services\Hook;
use Illuminate\Support\Facades\Event;
use Kokuu\Signin\Core\SignStatus;
use Kokuu\Signin\Host\DbSignLog;

/*
 * KokuuSkin 签到（kokuu-signin）
 *
 * 从 kokuu-credit 拆出的独立插件。
 *
 * 【职责边界】签到加分由内核 UserController@sign 完成（users.score += x,
 *   并更新 users.last_sign_at）。本插件只做两件事：
 *   1. 监听 user.sign.after，把签到补记进 credit_ledger 流水
 *      （内核加分不写流水，否则「今日获得/今日笔数」统计不到签到）
 *   2. 提供签到状态计算（供仪表盘展示）
 *
 * 【为什么独立】签到是通用能力，与积分系统耦合很浅（只共用 credit_ledger
 *   这一张流水表）。拆出来后：
 *   - 核心逻辑（SignStatus）可脱离框架单测
 *   - 没有积分系统时，签到仍能工作（只是流水记在本地表）
 *
 * 【架构】src/Core 可移植核心 / src/Host BlessingSkin 适配。
 */

return function ($plugin) {
    /*
     * ---- 签到流水：把内核签到加分记进 credit_ledger ----
     *
     * ⚠️ 绝不能用 kokuu_credit_ledger()：那个函数会 increment 余额，
     *    而内核 sign 已经加过分 → 会双加。
     *    这里只补记流水，balance_after 取内核已写好的 users.score。
     *
     * 幂等：event_id = sign:<uid>:<日期>，同一天重复触发撞唯一索引
     *   → insert 抛 1062 就忽略，反复点签到也不会双记。
     */
    Event::listen('user.sign.after', function ($acquiredScore = 0) {
        try {
            $user = auth()->user();
            if (! $user) {
                return;
            }
            $score = (int) $acquiredScore;
            if ($score <= 0) {
                return;
            }

            (new DbSignLog())->record(
                (int) $user->uid,
                $score,
                (int) $user->score,
                now()->toDateString()
            );
        } catch (\Throwable $e) {
            // 记账失败绝不能把签到本身搞挂
            error_log('[kokuu-signin] 签到流水记录失败: '.$e->getMessage());
        }
    });

    /*
     * ---- 仪表盘签到状态 ----
     *
     * 把签到状态注入仪表盘数据，供模板渲染签到卡片。
     * 用 View::share 让视图直接拿到 $kokuuSignStatus。
     */
    app(\Blessing\Filter::class)->add('grid:user.index', function ($grid) {
        try {
            $user = auth()->user();
            if ($user) {
                $status = SignStatus::compute($user->last_sign_at ?? null);
                \Illuminate\Support\Facades\View::share('kokuuSignStatus', $status);
            }
        } catch (\Throwable $e) {
            // 鉴权容器不可用时静默跳过
        }

        return $grid;
    });
};

