<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 奖品来源：读取可用奖品列表
 */
interface PrizeProvider
{
    /** @return Prize[] 已按 sort/id 排序、仅含 enabled */
    public function enabledPrizes(): array;

    /** 首次运行播种默认奖品（幂等） */
    public function ensureDefaults(): void;
}
