<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('sale_lines')) {
            return;
        }
        Schema::connection('tenant')->table('sale_lines', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('sale_lines', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('item_id')->constrained('batches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('sale_lines')) {
            return;
        }
        Schema::connection('tenant')->table('sale_lines', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('sale_lines', 'batch_id')) {
                $table->dropForeign(['batch_id']);
            }
        });
    }
};
