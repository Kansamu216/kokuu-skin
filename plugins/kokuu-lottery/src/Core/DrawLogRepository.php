<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽卡记录仓储
 */
interface DrawLogRepository
{
    public function lastDrawAt(int $uid): ?string;

    public function todayCount(int $uid): int;

    /**
     * 写一条抽卡记录
     *
     * v2 新增 rarity / asset：记录品质与资产类型，便于历史展示与统计。
     */
    public function insert(
        int $uid,
        ?int $prizeId,
        string $label,
        int $amount,
        string $via,
        string $ip,
        string $rarity = 'blue',
        string $asset = 'gold'
    ): int;
}

