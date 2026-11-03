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
 * 抽奖记录实现
 */
final class DbDrawLogRepository implements DrawLogRepository
{
    public function lastDrawAt(int $uid): ?string
    {
        Schema_::ensure();
        $v = DB::table('lottery_draws')->where('uid', $uid)->orderByDesc('id')->value('created_at');

        return $v === null ? null : (string) $v;
    }

    public function todayCount(int $uid): int
    {
        Schema_::ensure();

        return (int) DB::table('lottery_draws')
            ->where('uid', $uid)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function insert(int $uid, ?int $prizeId, string $label, int $amount, string $via, string $ip): int
    {
        Schema_::ensure();

        $id = DB::table('lottery_draws')->insertGetId([
            'uid' => $uid,
            'prize_id' => $prizeId,
            'prize_label' => $label,
            'amount' => $amount,
            'via' => $via,
            'ip' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $id;
    }
}
