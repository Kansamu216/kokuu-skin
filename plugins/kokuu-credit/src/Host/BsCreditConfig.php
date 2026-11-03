<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Host;

use Illuminate\Support\Facades\DB;
use KokuuStudio\KokuuCredit\Core\AccountRepository;
use KokuuStudio\KokuuCredit\Core\CreditConfig;
use KokuuStudio\KokuuCredit\Core\LedgerEntry;
use KokuuStudio\KokuuCredit\Core\LedgerRepository;
use KokuuStudio\KokuuCredit\Core\LedgerResult;

/**
 * 配置实现（读 options 表）
 */
final class BsCreditConfig implements CreditConfig
{
    public function ttl(): int
    {
        $v = app('options')->get('kokuu_credit_ttl');

        return ($v === null || $v === '') ? 300 : max(30, min(3600, (int) $v));
    }

    public function adminSecret(): string
    {
        return (string) (app('options')->get('kokuu_credit_admin_secret') ?? '');
    }
}
