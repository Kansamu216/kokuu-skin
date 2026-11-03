<?php

declare(strict_types=1);

namespace KokuuStudio\KokuuCredit\Core;

/**
 * 配置
 */
interface CreditConfig
{
    /** 签名有效期（秒） */
    public function ttl(): int;
    /** 管理平台密钥 */
    public function adminSecret(): string;
}
