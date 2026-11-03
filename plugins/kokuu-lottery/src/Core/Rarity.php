<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 奖品品质（稀有度）
 *
 * 对应配色（MC 风格，见 lottery.css）：
 *   blue   蓝色 —— 金粒（基础货币，最常见）
 *   purple 紫色 —— 钻石（稀有）
 *   gold   金色 —— 绿宝石（最稀有）
 */
final class Rarity
{
    public const BLUE = 'blue';
    public const PURPLE = 'purple';
    public const GOLD = 'gold';

    /** 品质顺序（越高越稀有），用于保底判定 */
    public const ORDER = [self::BLUE => 0, self::PURPLE => 1, self::GOLD => 2];

    public static function rank(string $rarity): int
    {
        return self::ORDER[$rarity] ?? 0;
    }

    public static function isAtLeast(string $rarity, string $min): bool
    {
        return self::rank($rarity) >= self::rank($min);
    }

    /** 中文名 */
    public static function label(string $rarity): string
    {
        return [
            self::BLUE => '普通',
            self::PURPLE => '稀有',
            self::GOLD => '传说',
        ][$rarity] ?? '普通';
    }
}

