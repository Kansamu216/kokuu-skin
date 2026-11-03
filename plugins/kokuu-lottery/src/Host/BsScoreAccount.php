<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Kokuu\Lottery\Core\DrawLogRepository;
use Kokuu\Lottery\Core\LotteryConfig;
use Kokuu\Lottery\Core\Prize;
use Kokuu\Lottery\Core\PrizeProvider;
use Kokuu\Lottery\Core\ScoreAccount;
use Kokuu\Lottery\Core\ScoreSink;
use Kokuu\Lottery\Core\Transaction;
use Kokuu\Lottery\Core\WalletRepository;

/**
 * 用户积分账户实现
 *
 * BlessingSkin 用 users.score 字段。
 */
final class BsScoreAccount implements ScoreAccount
{
    public function balance(int $uid): int
    {
        return (int) DB::table('users')->where('uid', $uid)->value('score');
    }

    public function lockBalance(int $uid): int
    {
        $row = DB::table('users')->where('uid', $uid)->lockForUpdate()->first();

        return (int) ($row->score ?? 0);
    }

    public function deduct(int $uid, int $amount): void
    {
        DB::table('users')->where('uid', $uid)->decrement('score', $amount);
    }
}
