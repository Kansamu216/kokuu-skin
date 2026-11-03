<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽奖次数账户（用兑换码换来的次数）
 */
interface WalletRepository
{
    public function balance(int $uid): int;
    public function add(int $uid, int $n): void;
    public function ensure(int $uid): void;
    /** 事务内锁定并返回当前次数 */
    public function lockForUpdate(int $uid): int;
    public function consumeOne(int $uid): void;
}
