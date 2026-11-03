<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 保底计数仓储
 *
 * 计数器语义：
 *   since_purple  距上次「紫或以上」已经抽了多少次（到 pityPurple 强制升紫）
 *   since_gold    距上次「金」已经抽了多少次（到 pityGold 强制出金）
 *
 * 注意：Service 层负责算新值，仓储只负责持久化。
 * 这样保底规则全部集中在一个可移植的纯逻辑里，便于单测。
 */
interface PityRepository
{
    /**
     * 事务内加锁读取
     *
     * @return array{since_purple:int, since_gold:int, total_draws:int}
     */
    public function lockForUpdate(int $uid): array;

    /**
     * 写回新计数
     *
     * @param int $sincePurple 新的「距上次紫」计数
     * @param int $sinceGold   新的「距上次金」计数
     * @param int $drawsAdded  本次新增抽数（用于累计 total_draws）
     */
    public function save(int $uid, int $sincePurple, int $sinceGold, int $drawsAdded): void;

    /** 不加锁读取（用于展示） */
    public function peek(int $uid): array;
}
