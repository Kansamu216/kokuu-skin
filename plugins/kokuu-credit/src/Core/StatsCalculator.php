<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Core;

/**
 * 统计聚合（纯函数：给定流水行，算出统计）
 */
final class StatsCalculator
{
    /**
     * @param array $rows 每行需含 delta / balance_after / source / created_at
     * @return array{total:int,today:int,events:int,totalAmount:int}
     */
    public static function summarize(array $rows, ?string $todayStart = null): array
    {
        $todayStart = $todayStart ?? date('Y-m-d 00:00:00');
        $today = 0;
        $events = 0;
        foreach ($rows as $r) {
            $delta = (int) ($r->delta ?? 0);
            $created = (string) ($r->created_at ?? '');
            if ($created >= $todayStart && $delta > 0) {
                $today += $delta;
                $events++;
            }
        }

        return [
            'today' => $today,
            'events' => $events,
        ];
    }
}
