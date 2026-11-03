<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Core;

/**
 * 流水仓储：原子写入 + event_id 幂等
 *
 * 实现方负责：
 *   1. 在事务内先占位插入（event_id 唯一索引冲突 → 幂等命中）
 *   2. 原子累加余额（increment 而非读出改写）
 *   3. 回填 balance_after
 */
interface LedgerRepository
{
    /**
     * 写入一条流水并原子更新余额。
     *
     * @return LedgerResult written=新余额；duplicate=事件已处理过
     */
    public function append(LedgerEntry $entry): LedgerResult;

    /** 某用户今日正向流水总额 */
    public function todayEarned(int $uid): int;

    /** 某用户今日流水笔数 */
    public function todayEvents(int $uid): int;

    /** 最近 N 条流水（\stdClass[]，字段见实现） */
    public function recent(int $uid, int $limit): array;

    /** 条件查询（供管理端） */
    public function query(?int $uid, int $limit): array;

    /** 全站统计 */
    public function stats(?int $uid): array;
}
