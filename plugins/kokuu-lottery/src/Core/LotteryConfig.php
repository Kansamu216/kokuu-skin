<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 配置接口 v2
 *
 * 新增保底线：每 N 抽必出紫 / 必出金。
 * 这些值都存 options 表，方便后台调整，也可留给后续服务器对接读取。
 */
interface LotteryConfig
{
    /** 单抽冷却秒数，0=不限 */
    public function cooldownSeconds(): int;

    /** 每日抽卡上限，0=不限 */
    public function dailyLimit(): int;

    /** 单抽消耗金粒 */
    public function drawCost(): int;

    /** 扣后必须保留的最低金粒 */
    public function minKeep(): int;

    /** 多少抽必出紫色或以上 */
    public function pityPurple(): int;

    /** 多少抽必出金色 */
    public function pityGold(): int;
}

