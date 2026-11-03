<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Kokuu\Lottery\Core\Prize;
use Kokuu\Lottery\Core\PrizeProvider;
use Kokuu\Lottery\Core\Rarity;

/**
 * 奖品来源实现 v2（MC 风格三品质奖池）
 *
 * 倍率设计（权重总和 10000，即万分比）：
 *
 *   蓝色 普通 — 金粒，共 8550（85.5%）
 *     金粒 ×20   权重 3000  (30%)     ← 小赚
 *     金粒 ×40   权重 2800  (28%)
 *     金粒 ×60   权重 1800  (18%)
 *     金粒 ×100  权重 950   ( 9.5%)
 *
 *   紫色 稀有 — 钻石，共 1400（14%）
 *     钻石 ×1    权重 1100  (11%)
 *     钻石 ×2    权重 300   ( 3%)
 *
 *   金色 传说 — 绿宝石，共 50（0.5%）
 *     绿宝石 ×1  权重 45    (0.45%)
 *     绿宝石 ×2  权重 5     (0.05%)
 *
 * 期望值核算（单抽成本 100 金粒，钻石估值 300 金粒、绿宝石估值 3000 金粒）：
 *   金粒期望 = 0.30*20 + 0.28*40 + 0.18*60 + 0.095*100
 *            = 6 + 11.2 + 10.8 + 9.5 = 37.5 金粒
 *   钻石期望 = 0.11*1 + 0.03*2 = 0.17 钻 × 300 = 51 金粒
 *   绿宝石期望 = 0.0045*1 + 0.0005*2 = 0.0055 × 3000 = 16.5 金粒
 *   合计期望 ≈ 105 金粒 / 100 金粒成本 → 约 105% 回报，
 *   配合每 10 抽保底紫、每 80 抽保底金，长期略高于成本，鼓励持续投入。
 */
final class DbPrizeProvider implements PrizeProvider
{
    public function enabledPrizes(): array
    {
        Schema_::ensure();

        $rows = DB::table('lottery_prizes')
            ->where('enabled', true)
            ->orderBy('sort')->orderBy('id')
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

        // 只在完全空表时播种
        if (DB::table('lottery_prizes')->count() > 0) {
            return;
        }

        $now = now();
        // label, amount, weight, rarity, asset, sort
        $rows = [
            ['金粒 ×20',   20,   3000, Rarity::BLUE,   'gold',    1],
            ['金粒 ×40',   40,   2800, Rarity::BLUE,   'gold',    2],
            ['金粒 ×60',   60,   1800, Rarity::BLUE,   'gold',    3],
            ['金粒 ×100',  100,   950, Rarity::BLUE,   'gold',    4],
            ['钻石 ×1',     1,   1100, Rarity::PURPLE, 'diamond', 5],
            ['钻石 ×2',     2,    300, Rarity::PURPLE, 'diamond', 6],
            ['绿宝石 ×1',   1,     45, Rarity::GOLD,   'emerald', 7],
            ['绿宝石 ×2',   2,      5, Rarity::GOLD,   'emerald', 8],
        ];

        foreach ($rows as $r) {
            DB::table('lottery_prizes')->insert([
                'label' => $r[0],
                'amount' => $r[1],
                'weight' => $r[2],
                'rarity' => $r[3],
                'asset' => $r[4],
                'sort' => $r[5],
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

