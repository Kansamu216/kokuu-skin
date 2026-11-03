<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽卡服务 v2（可移植核心）
 *
 * ══════════════════════════════════════════════════════════════════
 * 玩法规则（2026-11-03 按需求重做）
 * ══════════════════════════════════════════════════════════════════
 * 金粒（= 皮肤站积分 users.score）抽卡：
 *   蓝色 普通  → 获得金粒（回本为主）
 *   紫色 稀有  → 获得钻石（存 user_assets）
 *   金色 传说  → 获得绿宝石（存 user_assets）
 *
 * 保底：
 *   每 pityPurple 抽（默认 10）必出紫或以上
 *   每 pityGold   抽（默认 80）必出金
 *   出紫后 since_purple 归零；出金后两者都归零
 *
 * 十连：drawMany(uid, 10) 一个事务内连抽，共用保底计数，扣一次总价。
 */
final class LotteryService
{
    public function __construct(
        private PrizeProvider $prizes,
        private WalletRepository $wallets,
        private DrawLogRepository $logs,
        private ScoreAccount $scores,
        private AssetAccount $assets,
        private PityRepository $pity,
        private Transaction $tx,
        private ScoreSink $sink,
        private LotteryConfig $config,
    ) {
    }

    public function draw(int $uid, string $ip = ''): DrawResult
    {
        return $this->drawMany($uid, 1, $ip);
    }

    public function drawMany(int $uid, int $times, string $ip = ''): DrawResult
    {
        $times = max(1, min(10, $times));
        $this->prizes->ensureDefaults();
        $this->wallets->ensure($uid);

        $cost = $this->config->drawCost() * $times;
        $balance = $this->scores->balance($uid);

        if ($balance < $cost) {
            return $this->failWith('金粒不足：本次需 '.$cost.'，当前 '.$balance, $uid);
        }

        $outcome = $this->tx->run(function () use ($uid, $ip, $cost, $times): array {
            $balance = $this->scores->lockBalance($uid);
            if ($balance < $cost) {
                return ['ok' => false, 'msg' => '金粒不足：本次需 '.$cost.'，当前 '.$balance];
            }
            $this->scores->deduct($uid, $cost);

            $pity = $this->pity->lockForUpdate($uid);
            $sincePurple = (int) $pity['since_purple'];
            $sinceGold = (int) $pity['since_gold'];

            $prizes = $this->prizes->enabledPrizes();
            if (! $prizes) {
                return ['ok' => false, 'msg' => '奖池为空，请联系管理员'];
            }

            $results = [];
            for ($i = 0; $i < $times; $i++) {
                $sincePurple++;
                $sinceGold++;

                $forceGold = $sinceGold >= $this->config->pityGold();
                $forcePurple = ! $forceGold && $sincePurple >= $this->config->pityPurple();

                $prize = $this->pickPrize($prizes, $forceGold, $forcePurple);

                $gotGold = $prize->isGold();
                $gotPurple = Rarity::isAtLeast($prize->rarity, Rarity::PURPLE);

                if ($gotGold) {
                    $sinceGold = 0;
                    $sincePurple = 0;
                } elseif ($gotPurple) {
                    $sincePurple = 0;
                }

                $drawId = $this->logs->insert(
                    $uid,
                    $prize->id,
                    mb_substr($prize->label, 0, 80),
                    $prize->amount,
                    'cost',
                    mb_substr($ip, 0, 45),
                    $prize->rarity,
                    $prize->asset
                );

                $results[] = [
                    'drawId' => $drawId,
                    'label' => $prize->label,
                    'amount' => $prize->amount,
                    'rarity' => $prize->rarity,
                    'asset' => $prize->asset,
                ];
            }

            $this->pity->save($uid, $sincePurple, $sinceGold, $times);

            return ['ok' => true, 'results' => $results];
        });

        if (! $outcome['ok']) {
            return $this->failWith((string) $outcome['msg'], $uid);
        }

        // 入账（事务外，金粒走流水幂等，宝石直接加资产）
        foreach ($outcome['results'] as $r) {
            if ((int) $r['amount'] <= 0) {
                continue;
            }
            if ($r['asset'] === 'gold') {
                $this->sink->award($uid, (int) $r['amount'], 'draw:'.$r['drawId'], '抽卡：'.$r['label']);
            } else {
                $this->assets->add($uid, (string) $r['asset'], (int) $r['amount']);
            }
        }

        $bal = $this->scores->balance($uid);
        $keep = $this->config->minKeep();
        $first = $outcome['results'][0];

        return DrawResult::success(
            $times === 1 ? '抽到 '.$first['label'] : '十连完成',
            $times === 1 ? (string) $first['label'] : '',
            $times === 1 ? (int) $first['amount'] : 0,
            'cost',
            $times === 1 ? (int) $first['drawId'] : 0,
            $bal,
            $this->wallets->balance($uid),
            max(0, $bal - $keep),
            $this->config->cooldownSeconds(),
            $outcome['results'],
            $this->assets->all($uid)
        );
    }

    private function pickPrize(array $prizes, bool $forceGold, bool $forcePurple): Prize
    {
        if ($forceGold) {
            $gold = array_values(array_filter($prizes, fn (Prize $p) => $p->isGold()));
            if ($gold) {
                return $gold[random_int(0, count($gold) - 1)];
            }
        }

        if ($forcePurple) {
            $pool = array_values(array_filter($prizes, fn (Prize $p) => Rarity::isAtLeast($p->rarity, Rarity::PURPLE)));
            if ($pool) {
                return $pool[random_int(0, count($pool) - 1)];
            }
        }

        return self::pickWeighted($prizes) ?? $prizes[0];
    }

    /** @param Prize[] $prizes */
    public static function pickWeighted(array $prizes): ?Prize
    {
        $total = 0;
        foreach ($prizes as $p) {
            $total += max(0, $p->weight);
        }
        if ($total <= 0) {
            return null;
        }

        $roll = random_int(1, $total);
        $acc = 0;
        foreach ($prizes as $p) {
            $acc += max(0, $p->weight);
            if ($roll <= $acc) {
                return $p;
            }
        }

        return $prizes ? $prizes[array_key_last($prizes)] : null;
    }

    private function failWith(string $msg, int $uid): DrawResult
    {
        $keep = $this->config->minKeep();
        $bal = $this->scores->balance($uid);

        return DrawResult::fail($msg, max(0, $bal - $keep), $this->wallets->balance($uid), $this->config->cooldownSeconds());
    }

    public function walletBalance(int $uid): int
    {
        return $this->wallets->balance($uid);
    }

    public function cooldownLeft(int $uid): int
    {
        $cooldown = $this->config->cooldownSeconds();
        if ($cooldown <= 0) {
            return 0;
        }
        $last = $this->logs->lastDrawAt($uid);
        if ($last === null) {
            return 0;
        }
        $left = $cooldown - (time() - strtotime($last));

        return $left > 0 ? $left : 0;
    }

    public function oddsTable(): array
    {
        $this->prizes->ensureDefaults();
        $prizes = $this->prizes->enabledPrizes();

        $total = 0;
        foreach ($prizes as $p) {
            $total += max(0, $p->weight);
        }

        $out = [];
        foreach ($prizes as $p) {
            $out[] = [
                'label' => $p->label,
                'amount' => $p->amount,
                'rarity' => $p->rarity,
                'asset' => $p->asset,
                'weight' => $p->weight,
                'percent' => $total > 0 ? round(max(0, $p->weight) / $total * 100, 2) : 0,
            ];
        }

        return $out;
    }

    public function assets(int $uid): array
    {
        return $this->assets->all($uid);
    }

    public function pityState(int $uid): array
    {
        return $this->pity->peek($uid);
    }
}

