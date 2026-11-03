<?php

declare(strict_types=1);

namespace Kokuu\Signin\Host;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Kokuu\Signin\Core\SignLogRepository;

/**
 * 签到流水仓储（BlessingSkin 实现）
 *
 * 写入 credit_ledger 表（与积分插件共用同一张流水表）。
 * 表结构由 kokuu-credit 的 kokuu_credit_ensure_tables() 建，
 * 但这里也做一次幂等建表兜底 —— 万一积分插件未启用，签到仍能记录。
 */
final class DbSignLog implements SignLogRepository
{
    public function record(int $uid, int $score, int $balanceAfter, string $date): void
    {
        $this->ensureTable();

        // ⚠️ 绝不能用 kokuu_credit_ledger()：那个函数会 increment 余额，
        //    而内核 sign 已经加过分了 → 会双加。
        //    这里只补记流水，balance_after 取内核已写好的值。
        try {
            DB::table('credit_ledger')->insert([
                'event_id' => 'sign:'.$uid.':'.$date,
                'uid' => $uid,
                'delta' => $score,
                'balance_after' => $balanceAfter,
                'source' => 'sign',
                'ref' => null,
                'note' => '每日签到',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // 1062 = event_id 已存在（同一天重复签到）→ 幂等命中，静默忽略
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    private function ensureTable(): void
    {
        if (Schema::hasTable('credit_ledger')) {
            return;
        }

        // 积分插件未启用时兜底建表（结构与 kokuu-credit 一致）
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->increments('id');
            $table->string('event_id', 80)->unique();
            $table->unsignedInteger('uid');
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('source', 32);
            $table->string('ref', 191)->nullable();
            $table->string('note', 191)->nullable();
            $table->timestamps();
            $table->index('uid');
            $table->index('created_at');
        });
    }
}

