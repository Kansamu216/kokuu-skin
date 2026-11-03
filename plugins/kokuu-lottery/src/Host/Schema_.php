<?php

declare(strict_types=1);

namespace Kokuu\Lottery\Host;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * 表结构初始化（幂等）
 *
 * v2 新增：
 *   lottery_prizes.rarity   品质 blue/purple/gold
 *   lottery_prizes.asset    入账资产 gold/diamond/emerald
 *   lottery_draws.rarity    本次抽中的品质（用于历史展示）
 *   lottery_draws.asset     本次入账资产类型
 *   user_assets             用户钻石/绿宝石余额
 *   lottery_pity            保底计数器
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
                $table->string('via', 16)->default('cost');
                $table->string('ip', 45)->default('');
                $table->string('rarity', 16)->default('blue');
                $table->string('asset', 16)->default('gold');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['uid', 'created_at']);
            });
        } else {
            // 老表补列（幂等）
            if (! Schema::hasColumn('lottery_draws', 'rarity')) {
                Schema::table('lottery_draws', function (Blueprint $table) {
                    $table->string('rarity', 16)->default('blue')->after('ip');
                });
            }
            if (! Schema::hasColumn('lottery_draws', 'asset')) {
                Schema::table('lottery_draws', function (Blueprint $table) {
                    $table->string('asset', 16)->default('gold')->after('rarity');
                });
            }
        }

        if (! Schema::hasTable('lottery_wallets')) {
            Schema::create('lottery_wallets', function (Blueprint $table) {
                $table->increments('id');
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
                $table->string('rarity', 16)->default('blue');
                $table->string('asset', 16)->default('gold');
                $table->timestamps();
                $table->index(['enabled', 'sort']);
            });
        } else {
            if (! Schema::hasColumn('lottery_prizes', 'rarity')) {
                Schema::table('lottery_prizes', function (Blueprint $table) {
                    $table->string('rarity', 16)->default('blue')->after('enabled');
                });
            }
            if (! Schema::hasColumn('lottery_prizes', 'asset')) {
                Schema::table('lottery_prizes', function (Blueprint $table) {
                    $table->string('asset', 16)->default('gold')->after('rarity');
                });
            }
        }

        if (! Schema::hasTable('user_assets')) {
            Schema::create('user_assets', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('uid');
                $table->string('asset', 32);
                $table->bigInteger('amount')->default(0);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique(['uid', 'asset']);
            });
        }

        if (! Schema::hasTable('lottery_pity')) {
            Schema::create('lottery_pity', function (Blueprint $table) {
                $table->integer('uid')->primary();
                $table->integer('since_purple')->default(0);
                $table->integer('since_gold')->default(0);
                $table->integer('total_draws')->default(0);
                $table->dateTime('updated_at');
            });
        }
    }
}

