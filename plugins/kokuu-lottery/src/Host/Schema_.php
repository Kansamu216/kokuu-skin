<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Kokuu\Lottery\Core\DrawLogRepository;
use Kokuu\Lottery\Core\LotteryConfig;
use Kokuu\Lottery\Core\Prize;
use Kokuu\Lottery\Core\PrizeProvider;
use Kokuu\Lottery\Core\ScoreAccount;
use Kokuu\Lottery\Core\ScoreSink;
use Kokuu\Lottery\Core\Transaction;
use Kokuu\Lottery\Core\WalletRepository;

/**
 * 表结构初始化（幂等）
 */
final class Schema_
{
    public static function ensure(): void
    {
        if (! Schema::hasTable('lottery_draws')) {
            Schema::create('lottery_draws', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uid');
                $table->integer('prize_id')->nullable();
                $table->string('prize_label', 80)->default('');
                $table->integer('amount')->default(0);
                // code=用码获得次数；cost=花积分购买
                $table->string('via', 16)->default('cost');
                $table->string('ip', 45)->default('');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['uid', 'created_at']);
            });
        }

        if (! Schema::hasTable('lottery_wallets')) {
            Schema::create('lottery_wallets', function (Blueprint $table) {
                $table->increments('id');
                // ★ uid 唯一 → 并发抽奖靠它防重复扣
                $table->integer('uid')->unique();
                $table->integer('draws')->default(0);
                $table->integer('total_gained')->default(0);
                $table->integer('total_used')->default(0);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (! Schema::hasTable('lottery_prizes')) {
            Schema::create('lottery_prizes', function (Blueprint $table) {
                $table->increments('id');
                $table->string('label', 80);
                $table->integer('amount')->default(0);
                $table->integer('weight')->default(0);
                $table->integer('sort')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
                $table->index(['enabled', 'sort']);
            });
        }
    }
}
