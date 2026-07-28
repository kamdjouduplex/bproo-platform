<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['sales', 'expenses', 'loss_records', 'stock_movements'];

        foreach ($tables as $table) {
            if (!Schema::connection('tenant')->hasTable($table) || Schema::connection('tenant')->hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::connection('tenant')->table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unsignedBigInteger('store_id')->nullable()->after('id');
                $blueprint->index('store_id', "{$table}_store_id_index");
            });
        }
    }

    public function down(): void
    {
        $tables = ['sales', 'expenses', 'loss_records', 'stock_movements'];

        foreach ($tables as $table) {
            if (!Schema::connection('tenant')->hasTable($table) || !Schema::connection('tenant')->hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::connection('tenant')->table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex("{$table}_store_id_index");
                $blueprint->dropColumn('store_id');
            });
        }
    }
};
