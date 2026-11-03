<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Core;

/**
 * 流水条目（值对象）
 */
final class LedgerEntry
{
    public function __construct(
        public readonly string $eventId,
        public readonly int $uid,
        public readonly int $delta,
        public readonly string $source,
        public readonly ?string $ref = null,
        public readonly ?string $note = null,
    ) {
    }
}
