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
 * 加分出口实现
 *
 * 走 kokuu_credit_ledger（带 event_id 唯一索引 → 幂等）。
 * 若积分插件未启用，降级为直接累加（不记流水）。
 */
final class CreditLedgerSink implements ScoreSink
{
    public function award(int $uid, int $amount, string $eventId, string $note): void
    {
        if ($amount <= 0) {
            return;
        }

        if (function_exists('kokuu_credit_ledger')) {
            kokuu_credit_ledger($uid, $amount, 'lottery', $eventId, null, $note);
            return;
        }

        // 降级：积分插件不在，直接加到 users.score（无流水）
        DB::table('users')->where('uid', $uid)->increment('score', $amount);
    }
}
