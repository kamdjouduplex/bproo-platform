<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('article_types')
            && ! Schema::connection('tenant')->hasColumn('article_types', 'pricing_mode')) {
            Schema::connection('tenant')->table('article_types', function (Blueprint $table) {
                $table->string('pricing_mode', 32)->default('fixed')->after('is_active');
            });
        }

        if (Schema::connection('tenant')->hasTable('article_prices')
            && ! Schema::connection('tenant')->hasColumn('article_prices', 'pricing_mode')) {
            Schema::connection('tenant')->table('article_prices', function (Blueprint $table) {
                $table->string('pricing_mode', 32)->nullable()->after('price_per_kg');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('article_types')
            && Schema::connection('tenant')->hasColumn('article_types', 'pricing_mode')) {
            Schema::connection('tenant')->table('article_types', function (Blueprint $table) {
                $table->dropColumn('pricing_mode');
            });
        }

        if (Schema::connection('tenant')->hasTable('article_prices')
            && Schema::connection('tenant')->hasColumn('article_prices', 'pricing_mode')) {
            Schema::connection('tenant')->table('article_prices', function (Blueprint $table) {
                $table->dropColumn('pricing_mode');
            });
        }
    }
};
