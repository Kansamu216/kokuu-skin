<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Kokuu\Lottery\Core\DrawLogRepository;
use Kokuu\Lottery\Core\LotteryConfig;

/**
 * 抽卡记录仓储 v2（新增 rarity / asset 列）
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

    public function insert(
        int $uid,
        ?int $prizeId,
        string $label,
        int $amount,
        string $via,
        string $ip,
        string $rarity = 'blue',
        string $asset = 'gold'
    ): int {
        Schema_::ensure();

        return (int) DB::table('lottery_draws')->insertGetId([
            'uid' => $uid,
            'prize_id' => $prizeId,
            'prize_label' => $label,
            'amount' => $amount,
            'via' => $via,
            'ip' => $ip,
            'rarity' => $rarity,
            'asset' => $asset,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
