<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Kokuu\Lottery\Core\LotteryConfig;

/**
 * 配置实现 v2
 *
 * 读取 options 表，键前缀 kokuu_lottery_。
 * 默认值：单抽 100 金粒、每 10 抽保底紫、每 80 抽保底金、无冷却。
 */
final class BsLotteryConfig implements LotteryConfig
{
    private function get(string $key, int $default): int
    {
        $v = app('options')->get('kokuu_lottery_'.$key);

        return ($v === null || $v === '') ? $default : (int) $v;
    }

    public function cooldownSeconds(): int
    {
        return max(0, $this->get('draw_cooldown', 0));
    }

    public function dailyLimit(): int
    {
        return max(0, $this->get('daily_draw_limit', 0));
    }

    public function drawCost(): int
    {
        return max(1, $this->get('draw_cost', 100));
    }

    public function minKeep(): int
    {
        return max(0, $this->get('min_keep', 0));
    }

    public function pityPurple(): int
    {
        return max(1, $this->get('pity_purple', 10));
    }

    public function pityGold(): int
    {
        return max(1, $this->get('pity_gold', 80));
    }
}

