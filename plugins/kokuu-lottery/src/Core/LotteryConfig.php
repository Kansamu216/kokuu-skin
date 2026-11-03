<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 配置读取
 */
interface LotteryConfig
{
    /** 抽奖冷却秒数，0=不限 */
    public function cooldownSeconds(): int;
    /** 每日抽奖上限，0=不限 */
    public function dailyLimit(): int;
    /** 花积分购买的单价，0=关闭该入口 */
    public function drawCost(): int;
    /** 扣分后必须保留的最低余额 */
    public function minKeep(): int;
}
