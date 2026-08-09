<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_currencies')) {
            Schema::create('platform_currencies', function (Blueprint $table) {
                $table->string('code', 3)->primary();
                $table->string('name');
                $table->string('symbol', 16)->nullable();
                $table->unsignedTinyInteger('decimals')->default(2);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_currencies')) {
            Schema::create('tenant_currencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('currency_code', 3);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_enabled')->default(true);
                /** 1 unit of this currency = rate units of the tenant default currency */
                $table->decimal('exchange_rate_to_default', 18, 6)->default(1);
                $table->timestamps();
                $table->unique(['tenant_id', 'currency_code']);
                $table->foreign('currency_code')->references('code')->on('platform_currencies');
            });
        }

        $now = now();
        $seeds = [
            ['code' => 'XOF', 'name' => 'Franc CFA (UEMOA)', 'symbol' => 'F CFA', 'decimals' => 0, 'sort_order' => 10],
            ['code' => 'XAF', 'name' => 'Franc CFA (CEMAC)', 'symbol' => 'F CFA', 'decimals' => 0, 'sort_order' => 20],
            ['code' => 'CDF', 'name' => 'Franc congolais', 'symbol' => 'FC', 'decimals' => 0, 'sort_order' => 30],
            ['code' => 'USD', 'name' => 'Dollar américain', 'symbol' => '$', 'decimals' => 2, 'sort_order' => 40],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'sort_order' => 50],
            ['code' => 'GNF', 'name' => 'Franc guinéen', 'symbol' => 'FG', 'decimals' => 0, 'sort_order' => 60],
        ];

        foreach ($seeds as $row) {
            DB::table('platform_currencies')->updateOrInsert(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_currencies');
        Schema::dropIfExists('platform_currencies');
    }
};
