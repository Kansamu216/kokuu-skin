<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 加分出口：中奖后把积分写进账本
 *
 * BlessingSkin 实现走 kokuu_credit_ledger（带 event_id 幂等）；
 * 独立运行时可用 NullScoreSink 或简单累加实现。
 */
interface ScoreSink
{
    public function award(int $uid, int $amount, string $eventId, string $note): void;
}
