<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Kokuu\Lottery\Core\AssetAccount;
use Kokuu\Lottery\Core\PityRepository;

/**
 * 保底计数仓储 —— BlessingSkin 实现
 *
 * Service 算好新计数，这里只做持久化 + 行锁。
 */
final class DbPityRepository implements PityRepository
{
    public function lockForUpdate(int $uid): array
    {
        Schema_::ensure();
        $this->ensureRow($uid);

        $row = DB::table('lottery_pity')->where('uid', $uid)->lockForUpdate()->first();

        return [
            'since_purple' => (int) ($row->since_purple ?? 0),
            'since_gold' => (int) ($row->since_gold ?? 0),
            'total_draws' => (int) ($row->total_draws ?? 0),
        ];
    }

    public function save(int $uid, int $sincePurple, int $sinceGold, int $drawsAdded): void
    {
        Schema_::ensure();

        DB::table('lottery_pity')->where('uid', $uid)->update([
            'since_purple' => $sincePurple,
            'since_gold' => $sinceGold,
            'total_draws' => DB::raw('total_draws + '.$drawsAdded),
            'updated_at' => now(),
        ]);
    }

    public function peek(int $uid): array
    {
        Schema_::ensure();
        $row = DB::table('lottery_pity')->where('uid', $uid)->first();

        return [
            'since_purple' => (int) ($row->since_purple ?? 0),
            'since_gold' => (int) ($row->since_gold ?? 0),
            'total_draws' => (int) ($row->total_draws ?? 0),
        ];
    }

    private function ensureRow(int $uid): void
    {
        try {
            DB::table('lottery_pity')->insert([
                'uid' => $uid, 'since_purple' => 0, 'since_gold' => 0,
                'total_draws' => 0, 'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }
    }
}
