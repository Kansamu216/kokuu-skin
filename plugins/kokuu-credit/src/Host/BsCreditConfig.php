<?php

declare(strict_types=1);

namespace Kokuu\Credit\Host;

use Illuminate\Support\Facades\DB;
use Kokuu\Credit\Core\AccountRepository;
use Kokuu\Credit\Core\CreditConfig;
use Kokuu\Credit\Core\LedgerEntry;
use Kokuu\Credit\Core\LedgerRepository;
use Kokuu\Credit\Core\LedgerResult;

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
