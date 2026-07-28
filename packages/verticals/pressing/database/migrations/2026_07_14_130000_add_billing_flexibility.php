<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('article_prices')
            && ! Schema::connection('tenant')->hasColumn('article_prices', 'price_per_kg')) {
            Schema::connection('tenant')->table('article_prices', function (Blueprint $table) {
                $table->decimal('price_per_kg', 15, 2)->nullable()->after('amount');
            });
        }

        if (Schema::connection('tenant')->hasTable('pressing_orders')) {
            Schema::connection('tenant')->table('pressing_orders', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('pressing_orders', 'billing_mode')) {
                    $table->string('billing_mode', 32)->default('fixed')->after('status');
                }
                if (! Schema::connection('tenant')->hasColumn('pressing_orders', 'total_weight_kg')) {
                    $table->decimal('total_weight_kg', 10, 3)->nullable()->after('billing_mode');
                }
                if (! Schema::connection('tenant')->hasColumn('pressing_orders', 'weight_unit_price')) {
                    $table->decimal('weight_unit_price', 15, 2)->nullable()->after('total_weight_kg');
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('pressing_order_items')) {
            Schema::connection('tenant')->table('pressing_order_items', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('pressing_order_items', 'weight_kg')) {
                    $table->decimal('weight_kg', 10, 3)->nullable()->after('quantity');
                }
                if (! Schema::connection('tenant')->hasColumn('pressing_order_items', 'price_per_kg')) {
                    $table->decimal('price_per_kg', 15, 2)->nullable()->after('weight_kg');
                }
                if (! Schema::connection('tenant')->hasColumn('pressing_order_items', 'pricing_mode')) {
                    $table->string('pricing_mode', 32)->nullable()->after('price_per_kg');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('article_prices')
            && Schema::connection('tenant')->hasColumn('article_prices', 'price_per_kg')) {
            Schema::connection('tenant')->table('article_prices', function (Blueprint $table) {
                $table->dropColumn('price_per_kg');
            });
        }

        if (Schema::connection('tenant')->hasTable('pressing_orders')) {
            Schema::connection('tenant')->table('pressing_orders', function (Blueprint $table) {
                foreach (['billing_mode', 'total_weight_kg', 'weight_unit_price'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('pressing_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('pressing_order_items')) {
            Schema::connection('tenant')->table('pressing_order_items', function (Blueprint $table) {
                foreach (['weight_kg', 'price_per_kg', 'pricing_mode'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('pressing_order_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
