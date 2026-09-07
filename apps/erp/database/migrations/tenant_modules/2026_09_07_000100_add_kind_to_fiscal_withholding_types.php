<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_withholding_types')) {
            return;
        }

        if (! Schema::hasColumn('fiscal_withholding_types', 'kind')) {
            Schema::table('fiscal_withholding_types', function (Blueprint $table) {
                $table->string('kind', 20)->default('other')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fiscal_withholding_types') && Schema::hasColumn('fiscal_withholding_types', 'kind')) {
            Schema::table('fiscal_withholding_types', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
