<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽奖结果（值对象）
 */
final class DrawResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly string $label = '',
        public readonly int $amount = 0,
        public readonly string $via = '',
        public readonly int $drawId = 0,
        public readonly ?int $balance = null,
        public readonly ?int $drawsLeft = null,
    ) {
    }

    public static function fail(string $msg): self
    {
        return new self(false, $msg);
    }

    public static function success(
        string $msg,
        string $label,
        int $amount,
        string $via,
        int $drawId,
        $balance = null,
        $drawsLeft = null
    ): self {
        return new self(true, $msg, $label, $amount, $via, $drawId, $balance, $drawsLeft);
    }

    public function toArray(): array
    {
        return [
            'ok'      => $this->ok,
            'msg'     => $this->message,
            'label'   => $this->label,
            'amount'  => $this->amount,
            'via'     => $this->via,
            'drawId'  => $this->drawId,
            'balance' => $this->balance,
            'draws'   => $this->drawsLeft,
        ];
    }
}
