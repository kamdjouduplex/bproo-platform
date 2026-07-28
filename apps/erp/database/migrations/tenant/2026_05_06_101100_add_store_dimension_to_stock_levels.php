<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('stock_levels')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('stock_levels', 'store_id')) {
            Schema::connection('tenant')->table('stock_levels', function (Blueprint $table) {
                $table->unsignedBigInteger('store_id')->nullable()->after('id');
                $table->index('store_id', 'stock_levels_store_id_index');
            });
        }

        try {
            Schema::connection('tenant')->table('stock_levels', function (Blueprint $table) {
                $table->dropUnique('stock_levels_item_id_unique');
            });
        } catch (\Throwable $e) {
            // already dropped or named differently
        }

        $defaultStoreId = null;
        if (Schema::connection('tenant')->hasTable('stores')) {
            $defaultStoreId = DB::connection('tenant')->table('stores')->where('is_default', true)->value('id')
                ?: DB::connection('tenant')->table('stores')->orderBy('id')->value('id');
        }

        if ($defaultStoreId) {
            DB::connection('tenant')->table('stock_levels')->whereNull('store_id')->update(['store_id' => (int) $defaultStoreId]);
        }

        try {
            Schema::connection('tenant')->table('stock_levels', function (Blueprint $table) {
                $table->unique(['item_id', 'store_id'], 'stock_levels_item_store_unique');
            });
        } catch (\Throwable $e) {
            // keep migration resilient if index exists
        }
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('stock_levels')) {
            return;
        }

        try {
            Schema::connection('tenant')->table('stock_levels', function (Blueprint $table) {
                $table->dropUnique('stock_levels_item_store_unique');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::connection('tenant')->table('stock_levels', function (Blueprint $table) {
                $table->unique('item_id', 'stock_levels_item_id_unique');
            });
        } catch (\Throwable $e) {
        }
    }
};
