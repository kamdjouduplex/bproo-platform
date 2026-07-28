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

        if (Schema::connection('tenant')->hasColumn('sale_lines', 'metadata')) {
            return;
        }

        Schema::connection('tenant')->table('sale_lines', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasColumn('sale_lines', 'metadata')) {
            return;
        }

        Schema::connection('tenant')->table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
