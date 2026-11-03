<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Kokuu\Lottery\Core\DrawLogRepository;
use Kokuu\Lottery\Core\LotteryConfig;
use Kokuu\Lottery\Core\Prize;
use Kokuu\Lottery\Core\PrizeProvider;
use Kokuu\Lottery\Core\ScoreAccount;
use Kokuu\Lottery\Core\ScoreSink;
use Kokuu\Lottery\Core\Transaction;
use Kokuu\Lottery\Core\WalletRepository;

/**
 * 配置实现
 *
 * 读取 options 表，键前缀 kokuu_lottery_（从 kokuu_coupon_ 迁移）。
 * 首次读取时若新键不存在，回落到旧键，实现平滑迁移。
 */
final class BsLotteryConfig implements LotteryConfig
{
    private function get(string $key, int $default): int
    {
        $options = app('options');

        $v = $options->get('kokuu_lottery_'.$key);
        if ($v === null || $v === '') {
            // 平滑迁移：读旧的 kokuu_coupon_ 键
            $v = $options->get('kokuu_coupon_'.$key);
        }

        return ($v === null || $v === '') ? $default : (int) $v;
    }

    public function cooldownSeconds(): int
    {
        return max(0, $this->get('draw_cooldown', 3));
    }

    public function dailyLimit(): int
    {
        return max(0, $this->get('daily_draw_limit', 50));
    }

    public function drawCost(): int
    {
        return max(0, $this->get('draw_cost', 50));
    }

    /**
     * 最低保留积分。
     *
     * 唯一权威是 kokuu_exchange_min_keep（若积分兑换插件存在）。
     * 抽奖只读不写，避免两个扣分功能各设底线导致扣穿。
     */
    public function minKeep(): int
    {
        $v = app('options')->get('kokuu_exchange_min_keep');
        if ($v === null || $v === '') {
            return 100;
        }

        return max(0, (int) $v);
    }
}
