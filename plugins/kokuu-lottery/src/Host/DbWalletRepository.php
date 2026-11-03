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
 * 抽奖次数账户实现
 */
final class DbWalletRepository implements WalletRepository
{
    public function balance(int $uid): int
    {
        Schema_::ensure();
        $n = (int) DB::table('lottery_wallets')->where('uid', $uid)->value('draws');

        return max(0, $n);
    }

    public function add(int $uid, int $n): void
    {
        // ⚠️ 先 ensure 再 update：行不存在时直接 update 会静默 0 行
        $this->ensure($uid);

        DB::table('lottery_wallets')->where('uid', $uid)->update([
            'draws' => DB::raw('draws + '.(int) $n),
            'total_gained' => DB::raw('total_gained + '.(int) $n),
            'updated_at' => now(),
        ]);
    }

    public function ensure(int $uid): void
    {
        Schema_::ensure();

        // 幂等插入：撞唯一索引 = 已存在，忽略
        try {
            DB::table('lottery_wallets')->insert([
                'uid' => $uid,
                'draws' => 0,
                'total_gained' => 0,
                'total_used' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    public function lockForUpdate(int $uid): int
    {
        $row = DB::table('lottery_wallets')->where('uid', $uid)->lockForUpdate()->first();

        return max(0, (int) ($row->draws ?? 0));
    }

    public function consumeOne(int $uid): void
    {
        DB::table('lottery_wallets')->where('uid', $uid)->update([
            'draws' => DB::raw('draws - 1'),
            'total_used' => DB::raw('total_used + 1'),
            'updated_at' => now(),
        ]);
    }
}
