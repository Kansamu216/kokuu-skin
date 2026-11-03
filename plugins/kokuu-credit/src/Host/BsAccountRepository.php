<?php

declare(strict_types=1);

namespace Kokuu\Credit\Host;

use Illuminate\Support\Facades\DB;
use Kokuu\Credit\Core\AccountRepository;
use Kokuu\Credit\Core\CreditConfig;
use Kokuu\Credit\Core\LedgerEntry;
use Kokuu\Credit\Core\LedgerRepository;
use Kokuu\Credit\Core\LedgerResult;

/**
 * 用户账户实现（BlessingSkin 用 users.score）
 */
final class BsAccountRepository implements AccountRepository
{
    public function balance(int $uid): int
    {
        return (int) DB::table('users')->where('uid', $uid)->value('score');
    }

    public function addScore(int $uid, int $delta): int
    {
        DB::table('users')->where('uid', $uid)->increment('score', $delta);

        return $this->balance($uid);
    }
}
