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
