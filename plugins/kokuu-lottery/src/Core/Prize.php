<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 奖品（值对象）v2
 *
 * 相比 v1 增加：
 *   rarity  品质 blue/purple/gold
 *   asset   入账资产类型 gold/diamond/emerald
 *   isJackpot 是否「大奖」（决定动画走金光还是紫光）
 */
final class Prize
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $amount,
        public readonly int $weight,
        public readonly int $sort = 0,
        public readonly string $rarity = Rarity::BLUE,
        public readonly string $asset = 'gold',
    ) {
    }

    public function isGold(): bool
    {
        return $this->rarity === Rarity::GOLD;
    }

    public function isPurple(): bool
    {
        return $this->rarity === Rarity::PURPLE;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['label'] ?? ''),
            (int) ($row['amount'] ?? 0),
            (int) ($row['weight'] ?? 0),
            (int) ($row['sort'] ?? 0),
            (string) ($row['rarity'] ?? Rarity::BLUE),
            (string) ($row['asset'] ?? 'gold'),
        );
    }
}

