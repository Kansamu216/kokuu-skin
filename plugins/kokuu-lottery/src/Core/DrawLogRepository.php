<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽奖记录
 */
interface DrawLogRepository
{
    public function lastDrawAt(int $uid): ?string;
    public function todayCount(int $uid): int;
    public function insert(int $uid, ?int $prizeId, string $label, int $amount, string $via, string $ip): int;
}
