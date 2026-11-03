<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 抽卡结果（值对象）v2
 *
 * ⚠️ toArray() 字段名必须与 lottery.js 严格对齐。
 *    新增 results（十连结果数组）与 assets（资产快照）。
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
        public readonly ?int $spendable = null,
        public readonly int $cooldown = 0,
        /** @var array<array{drawId:int,label:string,amount:int,rarity:string,asset:string}> */
        public readonly array $results = [],
        /** @var array<string,int> asset => amount */
        public readonly array $assets = [],
    ) {
    }

    public static function fail(
        string $msg,
        ?int $spendable = null,
        ?int $drawsLeft = null,
        int $cooldown = 0
    ): self {
        return new self(false, $msg, '', 0, '', 0, null, $drawsLeft, $spendable, $cooldown);
    }

    public static function success(
        string $msg,
        string $label,
        int $amount,
        string $via,
        int $drawId,
        $balance = null,
        $drawsLeft = null,
        ?int $spendable = null,
        int $cooldown = 0,
        array $results = [],
        array $assets = []
    ): self {
        return new self(
            true, $msg, $label, $amount, $via, $drawId,
            $balance === null ? null : (int) $balance,
            $drawsLeft === null ? null : (int) $drawsLeft,
            $spendable,
            $cooldown,
            $results,
            $assets
        );
    }

    public function toArray(): array
    {
        return [
            'ok'        => $this->ok,
            'msg'       => $this->message,
            'label'     => $this->label,
            'amount'    => $this->amount,
            'via'       => $this->via,
            'drawId'    => $this->drawId,
            'balance'   => $this->balance,
            'draws'     => $this->drawsLeft,
            'spendable' => $this->spendable,
            'cooldown'  => $this->cooldown,
            'results'   => $this->results,
            'assets'    => $this->assets,
        ];
    }
}

