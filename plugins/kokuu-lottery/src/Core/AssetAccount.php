<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Core;

/**
 * 资产账户接口（钻石 / 绿宝石 / 金粒）
 *
 * 与 ScoreAccount 分离：金粒就是皮肤站积分（users.score），
 * 钻石和绿宝石是新增资产，存在 user_assets 表。
 *
 * 这个接口是留给「后续对接服务器积分管理」的扩展点 ——
 * 未来接入真实服务器时，只需替换 Host 层实现，Core 不动。
 */
interface AssetAccount
{
    public function balance(int $uid, string $asset): int;

    public function add(int $uid, string $asset, int $amount): void;

    /** @return array<string,int> asset => amount */
    public function all(int $uid): array;
}
