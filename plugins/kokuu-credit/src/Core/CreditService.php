<?php

declare(strict_types=1);

namespace Kokuu\Credit\Core;

/**
 * 积分账本服务（可移植核心）
 *
 * 组合仓储完成业务：写流水（幂等）、查统计。
 * 不依赖任何宿主框架，仓储通过构造函数注入。
 */
final class CreditService
{
    public function __construct(
        private LedgerRepository $ledger,
        private AccountRepository $accounts,
    ) {
    }

    /**
     * 记一笔流水并原子更新余额。
     *
     * 幂等：同一 event_id 重复调用只会生效一次。
     *
     * @return LedgerResult
     */
    public function record(
        int $uid,
        int $delta,
        string $source,
        string $eventId,
        ?string $ref = null,
        ?string $note = null
    ): LedgerResult {
        return $this->ledger->append(
            new LedgerEntry($eventId, $uid, $delta, $source, $ref, $note)
        );
    }

    public function balance(int $uid): int
    {
        return $this->accounts->balance($uid);
    }

    /**
     * 用户统计：今日获得、今日笔数。
     *
     * @return array{today:int,events:int}
     */
    public function userStats(int $uid): array
    {
        return [
            'today' => $this->ledger->todayEarned($uid),
            'events' => $this->ledger->todayEvents($uid),
        ];
    }
}

