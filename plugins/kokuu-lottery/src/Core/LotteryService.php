<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽奖服务（可移植核心）
 *
 * 全部业务规则集中在此，不依赖任何宿主框架。
 * 依赖通过构造函数注入，便于单测与跨框架复用。
 *
 * 关键设计（沿用原 kokuu-coupon 的实战经验）：
 *
 * 1. 扣分「两道闸」
 *    事务外预检 → 给友好提示（区分「积分不足」与「需保留 N 积分」）
 *    事务内 lockForUpdate 后再查 → 防并发扣穿底仓
 *    缺第二道时两个并发请求都通过预检，各扣一次。
 *
 * 2. 抽奖次数 lockForUpdate
 *    否则两个请求都读到 draws=1，各抽一次 → 一次机会抽两次。
 *
 * 3. 随机源用 random_int（CSPRNG）
 *    奖品是真实积分，可预测 = 可被脚本刷大奖。
 *
 * 4. 中奖加分放事务外
 *    ScoreSink 内部自带事务，嵌进来会变成 savepoint（虽正确但多一层嵌套）。
 *
 * 5. event_id = draw:<id>
 *    保证加分幂等，重放不会重复入账。
 */
final class LotteryService
{
    public function __construct(
        private PrizeProvider $prizes,
        private WalletRepository $wallets,
        private DrawLogRepository $logs,
        private ScoreAccount $scores,
        private Transaction $tx,
        private ScoreSink $sink,
        private LotteryConfig $config,
    ) {
    }

    /**
     * 抽奖一次。
     *
     * 两种消耗来源（via）：
     *   code → 用兑换码换来的次数，不扣积分
     *   cost → 花积分买一次，会扣 ScoreAccount
     */
    public function draw(int $uid, string $ip = ''): DrawResult
    {
        $this->prizes->ensureDefaults();
        $this->wallets->ensure($uid);

        /* ============ 事务外预检：只为友好提示 ============ */
        $cooldown = $this->config->cooldownSeconds();
        if ($cooldown > 0) {
            $last = $this->logs->lastDrawAt($uid);
            if ($last !== null) {
                $left = $cooldown - (time() - strtotime($last));
                if ($left > 0) {
                    return DrawResult::fail('冷却中，请 '.$left.' 秒后再试');
                }
            }
        }

        $dailyLimit = $this->config->dailyLimit();
        if ($dailyLimit > 0 && $this->logs->todayCount($uid) >= $dailyLimit) {
            return DrawResult::fail('今日抽奖次数已达上限（'.$dailyLimit.'次）');
        }

        $drawsLeft = $this->wallets->balance($uid);
        $cost = $this->config->drawCost();

        if ($drawsLeft <= 0) {
            if ($cost <= 0) {
                return DrawResult::fail('当前没有可用的抽奖次数，且未开放积分抽奖');
            }
            $balance = $this->scores->balance($uid);
            $keep = $this->config->minKeep();
            $spendable = $balance - $keep;
            if ($spendable < $cost) {
                return DrawResult::fail(
                    $spendable < 0
                        ? '抽奖需保留 '.$keep.' 积分，当前余额 '.$balance.' 已不足'
                        : '积分不足：本次需 '.$cost.'，扣除保留的 '.$keep.' 后可用 '.$spendable
                );
            }
        }

        /* ================= 事务开始 ================= */
        $outcome = $this->tx->run(function () use ($uid, $ip, $cost): array {
            // 次数账户加锁 → 并发抽奖串行化
            $this->wallets->ensure($uid);
            $curDraws = $this->wallets->lockForUpdate($uid);
            $via = $curDraws > 0 ? 'code' : 'cost';

            if ($via === 'code') {
                $this->wallets->consumeOne($uid);
            } else {
                /* ★ 第二道闸：加锁后再查余额 */
                $keep = $this->config->minKeep();
                $balance = $this->scores->lockBalance($uid);
                if (($balance - $keep) < $cost) {
                    return [
                        'ok' => false,
                        'msg' => ($balance - $keep) < 0
                            ? '抽奖需保留 '.$keep.' 积分，当前余额 '.$balance.' 已不足'
                            : '积分不足：本次需 '.$cost.'，扣除保留的 '.$keep.' 后可用 '.($balance - $keep),
                    ];
                }
                $this->scores->deduct($uid, $cost);
            }

            // 奖品在事务内读，避免「已扣次数」与配置不一致
            $prizes = $this->prizes->enabledPrizes();
            $prize = self::pickWeighted($prizes);
            $label = $prize?->label ?? '谢谢参与';
            $amount = $prize?->amount ?? 0;

            $drawId = $this->logs->insert(
                $uid,
                $prize?->id,
                mb_substr($label, 0, 80),
                $amount,
                $via,
                mb_substr($ip, 0, 45)
            );

            return [
                'ok' => true,
                'label' => $label,
                'amount' => $amount,
                'via' => $via,
                'drawId' => $drawId,
            ];
        });

        if (! $outcome['ok']) {
            return DrawResult::fail((string) $outcome['msg']);
        }

        /* ---- 中奖加分（事务外，幂等键 draw:<id>）---- */
        if ((int) $outcome['amount'] > 0) {
            $this->sink->award(
                $uid,
                (int) $outcome['amount'],
                'draw:'.$outcome['drawId'],
                '抽奖：'.$outcome['label']
            );
        }

        return DrawResult::success(
            '抽到 '.$outcome['label'],
            (string) $outcome['label'],
            (int) $outcome['amount'],
            (string) $outcome['via'],
            (int) $outcome['drawId'],
            $this->scores->balance($uid),
            $this->wallets->balance($uid)
        );
    }

    /**
     * 按权重抽一个奖品。
     *
     * @param Prize[] $prizes
     */
    public static function pickWeighted(array $prizes): ?Prize
    {
        $total = 0;
        foreach ($prizes as $p) {
            $total += max(0, $p->weight);
        }
        if ($total <= 0) {
            return null;
        }

        // random_int 是 CSPRNG：奖品是真实积分，必须不可预测
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

    /**
     * 奖品概率表（给用户看的展示数据）
     *
     * 全部算好再传视图：模板层不做业务算术。
     *
     * @return array<int, array{label:string,amount:int,weight:int,percent:float,cumulative:float}>
     */
    public function oddsTable(): array
    {
        $this->prizes->ensureDefaults();
        $prizes = $this->prizes->enabledPrizes();

        $total = 0;
        foreach ($prizes as $p) {
            $total += max(0, $p->weight);
        }

        $out = [];
        $cum = 0.0;
        foreach ($prizes as $p) {
            $percent = $total > 0 ? max(0, $p->weight) / $total * 100 : 0.0;
            $cum += $percent;
            $out[] = [
                'label' => $p->label,
                'amount' => $p->amount,
                'weight' => $p->weight,
                'percent' => round($percent, 2),
                'cumulative' => round($cum, 2),
            ];
        }

        return $out;
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
}

