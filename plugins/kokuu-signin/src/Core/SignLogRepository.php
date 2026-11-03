<?php

declare(strict_types=1);

namespace Kokuu\Signin\Core;

/**
 * 签到记录仓储接口（可移植核心）
 *
 * 宿主实现负责把签到流水写进实际存储。
 * BlessingSkin 实现见 src/Host/DbSignLog.php。
 */
interface SignLogRepository
{
    /**
     * 记录一次签到。
     *
     * 约定：实现方必须保证同一 uid + 日期只记一条（幂等）。
     * 重复调用不应抛异常，也不应产生重复记录。
     *
     * @param int    $uid          用户 id
     * @param int    $score        本次签到获得的积分
     * @param int    $balanceAfter 签到后的余额（内核已加完，这里只读）
     * @param string $date         日期（Y-m-d）
     */
    public function record(int $uid, int $score, int $balanceAfter, string $date): void;
}

