<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 奖品（值对象，不持有 DB 行）
 */
final class Prize
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $amount,
        public readonly int $weight,
        public readonly int $sort = 0,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['label'] ?? ''),
            (int) ($row['amount'] ?? 0),
            (int) ($row['weight'] ?? 0),
            (int) ($row['sort'] ?? 0),
        );
    }
}
