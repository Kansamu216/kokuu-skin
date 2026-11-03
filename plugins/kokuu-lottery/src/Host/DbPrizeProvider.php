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
 * 奖品来源实现
 */
final class DbPrizeProvider implements PrizeProvider
{
    public function enabledPrizes(): array
    {
        Schema_::ensure();

        $rows = DB::table('lottery_prizes')
            ->where('enabled', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = Prize::fromArray((array) $r);
        }

        return $out;
    }

    public function ensureDefaults(): void
    {
        Schema_::ensure();

        if (DB::table('lottery_prizes')->count() > 0) {
            return;
        }

        $now = now();
        // 权重 1000 份，期望约 100 积分/抽
        $rows = [
            ['谢谢参与', 0, 500],
            ['5 积分', 5, 250],
            ['10 积分', 10, 150],
            ['20 积分', 20, 70],
            ['50 积分', 50, 25],
            ['100 积分', 100, 5],
        ];

        $i = 0;
        foreach ($rows as $r) {
            $i++;
            DB::table('lottery_prizes')->insert([
                'label' => $r[0],
                'amount' => $r[1],
                'weight' => $r[2],
                'sort' => $i,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
