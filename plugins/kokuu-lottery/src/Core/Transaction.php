<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 事务边界（宿主提供，核心不感知具体实现）
 */
interface Transaction
{
    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function run(callable $fn);
}
