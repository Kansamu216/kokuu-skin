<?php

declare(strict_types=1);

namespace Kokuu\Credit\Core;

/**
 * 用户账户
 *
 * 只暴露积分核心真正需要的操作。注意 adjust 是「原子加减」，
 * 不是「读出旧值算完写回」——后者在并发下会丢更新。
 */
interface AccountRepository
{
    public function balance(int $uid): int;
    public function addScore(int $uid, int $delta): int;
}
