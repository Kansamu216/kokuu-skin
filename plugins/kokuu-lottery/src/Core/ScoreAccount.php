<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 用户积分账户
 *
 * ⚠️ 这是「宿主账户」抽象：BlessingSkin 用 users.score，
 *    其他系统可实现为任意余额账本。
 */
interface ScoreAccount
{
    public function balance(int $uid): int;
    /** 事务内锁定并返回余额 */
    public function lockBalance(int $uid): int;
    public function deduct(int $uid, int $amount): void;
}
