<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Core;

/**
 * 来源标签（纯映射，无 IO）
 */
final class SourceLabels
{
    /** @return array<string,string> */
    public static function all(): array
    {
        return [
            'sign' => '每日签到',
            'manual' => '管理员手动',
            'lottery' => '抽奖',
            'coupon' => '兑换码',
            'player' => '创建角色',
            'closet' => '衣柜',
            'texture' => '上传材质',
            'report' => '举报',
        ];
    }

    public static function label(string $source): string
    {
        return self::all()[$source] ?? $source;
    }
}
