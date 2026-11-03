<?php

declare(strict_types=1);

namespace Kokuu\Credit\Core;

/**
 * 流水结果
 */
final class LedgerResult
{
    private function __construct(
        public readonly bool $written,
        public readonly int $balanceAfter,
    ) {
    }

    public static function written(int $balanceAfter): self
    {
        return new self(true, $balanceAfter);
    }

    public static function duplicate(): self
    {
        return new self(false, 0);
    }
}
