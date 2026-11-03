<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Kokuu\Lottery\Core\AssetAccount;

/**
 * 资产账户（钻石 / 绿宝石）—— BlessingSkin 实现
 *
 * 金粒即 users.score，由 ScoreAccount / CreditLedgerSink 处理；
 * 这里只管 user_assets 表里的钻石与绿宝石。
 */
final class DbAssetAccount implements AssetAccount
{
    public function balance(int $uid, string $asset): int
    {
        Schema_::ensure();

        return (int) DB::table('user_assets')
            ->where('uid', $uid)->where('asset', $asset)->value('amount');
    }

    public function add(int $uid, string $asset, int $amount): void
    {
        if ($amount === 0) {
            return;
        }
        Schema_::ensure();
        $now = now();

        $affected = DB::table('user_assets')
            ->where('uid', $uid)->where('asset', $asset)
            ->update(['amount' => DB::raw('amount + '.$amount), 'updated_at' => $now]);

        if ($affected === 0) {
            try {
                DB::table('user_assets')->insert([
                    'uid' => $uid,
                    'asset' => $asset,
                    'amount' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    DB::table('user_assets')
                        ->where('uid', $uid)->where('asset', $asset)
                        ->update(['amount' => DB::raw('amount + '.$amount), 'updated_at' => $now]);
                } else {
                    throw $e;
                }
            }
        }
    }

    public function all(int $uid): array
    {
        Schema_::ensure();

        $rows = DB::table('user_assets')->where('uid', $uid)->get(['asset', 'amount']);
        $out = ['gold' => 0, 'diamond' => 0, 'emerald' => 0];
        foreach ($rows as $r) {
            $out[(string) $r->asset] = (int) $r->amount;
        }

        return $out;
    }
}

