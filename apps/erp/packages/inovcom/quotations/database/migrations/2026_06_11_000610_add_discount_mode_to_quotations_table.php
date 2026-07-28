<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotations')) {
            return;
        }

        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotations', 'discount_mode')) {
                $table->string('discount_mode', 10)->default('percent')->after('discount_percent');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotations')) {
            return;
        }

        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotations', 'discount_mode')) {
                $table->dropColumn('discount_mode');
            }
        });
    }
};
