<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Host;

use Illuminate\Support\Facades\DB;
use KokuuStudio\KokuuCredit\Core\AccountRepository;
use KokuuStudio\KokuuCredit\Core\CreditConfig;
use KokuuStudio\KokuuCredit\Core\LedgerEntry;
use KokuuStudio\KokuuCredit\Core\LedgerRepository;
use KokuuStudio\KokuuCredit\Core\LedgerResult;

/**
 * 积分流水仓储（BlessingSkin 实现）
 *
 * 核心是 append()：
 *   1. 事务内先占位插入（event_id 唯一索引冲突 → 幂等命中）
 *   2. increment 原子自增余额
 *   3. 回填 balance_after
 *
 * 为什么用 increment 而非「读出来 + 完写回」：
 *   两个并发请求都读到同一旧余额 → 其中一个的增量被覆盖。
 */
final class DbLedgerRepository implements LedgerRepository
{
    public function append(LedgerEntry $entry): LedgerResult
    {
        return DB::transaction(function () use ($entry) {
            // 先占坑：event_id 冲突即已处理过
            try {
                DB::table('credit_ledger')->insert([
                    'event_id' => $entry->eventId,
                    'uid' => $entry->uid,
                    'delta' => 0,          // 占位，稍后回填
                    'balance_after' => 0,
                    'source' => $entry->source,
                    'ref' => $entry->ref,
                    'note' => $entry->note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    return LedgerResult::duplicate();
                }
                throw $e;
            }

            $ledgerId = DB::table('credit_ledger')
                ->where('event_id', $entry->eventId)
                ->value('id');

            // 原子自增，再读回新余额
            DB::table('users')->where('uid', $entry->uid)->increment('score', $entry->delta);
            $balance = (int) DB::table('users')->where('uid', $entry->uid)->value('score');

            DB::table('credit_ledger')->where('id', $ledgerId)->update([
                'delta' => $entry->delta,
                'balance_after' => $balance,
            ]);

            return LedgerResult::written($balance);
        });
    }

    public function todayEarned(int $uid): int
    {
        return (int) DB::table('credit_ledger')
            ->where('uid', $uid)
            ->where('delta', '>', 0)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('delta');
    }

    public function todayEvents(int $uid): int
    {
        return (int) DB::table('credit_ledger')
            ->where('uid', $uid)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function recent(int $uid, int $limit): array
    {
        return DB::table('credit_ledger')
            ->where('uid', $uid)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function query(?int $uid, int $limit): array
    {
        $q = DB::table('credit_ledger')->orderByDesc('id')->limit($limit);
        if ($uid !== null && $uid > 0) {
            $q->where('uid', $uid);
        }

        return $q->get()->all();
    }

    public function stats(?int $uid): array
    {
        $q = DB::table('credit_ledger');
        if ($uid !== null && $uid > 0) {
            $q->where('uid', $uid);
        }
        $rows = $q->get(['delta', 'created_at'])->all();

        return \KokuuStudio\KokuuCredit\Core\StatsCalculator::summarize($rows);
    }
}
