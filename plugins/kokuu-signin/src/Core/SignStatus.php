<?php

declare(strict_types=1);

namespace Kokuu\Signin\Core;

/**
 * 签到状态计算（可移植核心）
 *
 * ⚠️ 本文件不得 import 任何宿主命名空间。
 * 只做纯逻辑：给定「上次签到时间」与「现在」，判断今天是否已签、给出文案。
 *
 * 为什么单独抽出来：这套「今天签没签」的判断是最容易踩坑的部分
 *   （时区、datetime 字符串 vs 时间戳、跨日边界），
 *   把它变成不依赖框架的纯函数后，可以用单元测试穷举边界情况。
 */
final class SignStatus
{
    /**
     * 计算签到状态。
     *
     * @param string|null $lastSignAt 上次签到时间（datetime 字符串或 null）
     * @param string      $now        当前时间（datetime 字符串，便于测试注入）
     *
     * @return array{signed:bool, at:?string, text:string}
     */
    public static function compute(?string $lastSignAt, ?string $now = null): array
    {
        $nowTs = $now !== null ? strtotime($now) : time();
        if ($nowTs === false) {
            $nowTs = time();
        }

        if ($lastSignAt === null || trim($lastSignAt) === '') {
            return ['signed' => false, 'at' => null, 'text' => '尚未签到'];
        }

        $lastTs = strtotime($lastSignAt);
        if ($lastTs === false) {
            // 脏数据不该让整个仪表盘 500
            return ['signed' => false, 'at' => null, 'text' => '尚未签到'];
        }

        // 用「自然日」比较，而不是时间差 —— 避免时区导致跨日误判
        $signedToday = date('Y-m-d', $lastTs) === date('Y-m-d', $nowTs);

        return [
            'signed' => $signedToday,
            'at' => date('Y-m-d H:i:s', $lastTs),
            'text' => $signedToday
                ? '今日已签到 · '.date('H:i', $lastTs)
                : '上次签到 '.self::humanDiff($nowTs - $lastTs),
        ];
    }

    /**
     * 人类可读的时间差。
     */
    private static function humanDiff(int $seconds): string
    {
        if ($seconds < 60) {
            return '刚刚';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' 分钟前';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600).' 小时前';
        }
        $days = intdiv($seconds, 86400);
        if ($days < 30) {
            return $days.' 天前';
        }
        if ($days < 365) {
            return intdiv($days, 30).' 个月前';
        }

        return intdiv($days, 365).' 年前';
    }
}

