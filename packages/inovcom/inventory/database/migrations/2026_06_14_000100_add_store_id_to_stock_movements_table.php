<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('stock_movements')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('stock_movements', 'store_id')) {
            return;
        }

        Schema::connection('tenant')->table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->index('store_id', 'stock_movements_store_id_index');
        });

        if (!Schema::connection('tenant')->hasTable('stores')) {
            return;
        }

        $defaultStoreId = DB::connection('tenant')->table('stores')->where('is_default', true)->value('id')
            ?: DB::connection('tenant')->table('stores')->orderBy('id')->value('id');

        if ($defaultStoreId) {
            DB::connection('tenant')->table('stock_movements')->whereNull('store_id')->update([
                'store_id' => (int) $defaultStoreId,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('stock_movements')
            || !Schema::connection('tenant')->hasColumn('stock_movements', 'store_id')) {
            return;
        }

        Schema::connection('tenant')->table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_store_id_index');
            $table->dropColumn('store_id');
        });
    }
};
