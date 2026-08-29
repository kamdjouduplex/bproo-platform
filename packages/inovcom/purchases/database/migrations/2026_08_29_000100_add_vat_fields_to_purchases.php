<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('purchase_orders')) {
            Schema::connection('tenant')->table('purchase_orders', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'has_vat')) {
                    $table->boolean('has_vat')->default(false)->after('notes');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'price_mode')) {
                    $table->string('price_mode', 10)->default('ht')->after('has_vat');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'vat_rate')) {
                    $table->decimal('vat_rate', 8, 4)->default(0)->after('price_mode');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'vat_deductible')) {
                    $table->boolean('vat_deductible')->default(true)->after('vat_rate');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'vat_amount')) {
                    $table->decimal('vat_amount', 15, 2)->default(0)->after('subtotal');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'total_ht')) {
                    $table->decimal('total_ht', 15, 2)->default(0)->after('vat_amount');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_orders', 'total_ttc')) {
                    $table->decimal('total_ttc', 15, 2)->default(0)->after('total_ht');
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('purchase_lines')) {
            Schema::connection('tenant')->table('purchase_lines', function (Blueprint $table) {
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'entered_unit_price')) {
                    $table->decimal('entered_unit_price', 12, 2)->nullable()->after('unit_price');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'unit_price_ht')) {
                    $table->decimal('unit_price_ht', 12, 2)->nullable()->after('entered_unit_price');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'unit_price_ttc')) {
                    $table->decimal('unit_price_ttc', 12, 2)->nullable()->after('unit_price_ht');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'vat_rate')) {
                    $table->decimal('vat_rate', 8, 4)->default(0)->after('unit_price_ttc');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'vat_amount')) {
                    $table->decimal('vat_amount', 15, 2)->default(0)->after('vat_rate');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'line_total_ht')) {
                    $table->decimal('line_total_ht', 15, 2)->nullable()->after('line_total');
                }
                if (! Schema::connection('tenant')->hasColumn('purchase_lines', 'line_total_ttc')) {
                    $table->decimal('line_total_ttc', 15, 2)->nullable()->after('line_total_ht');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('purchase_orders')) {
            Schema::connection('tenant')->table('purchase_orders', function (Blueprint $table) {
                $cols = ['has_vat', 'price_mode', 'vat_rate', 'vat_deductible', 'vat_amount', 'total_ht', 'total_ttc'];
                $drop = array_values(array_filter($cols, fn ($col) => Schema::connection('tenant')->hasColumn('purchase_orders', $col)));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('purchase_lines')) {
            Schema::connection('tenant')->table('purchase_lines', function (Blueprint $table) {
                $cols = ['entered_unit_price', 'unit_price_ht', 'unit_price_ttc', 'vat_rate', 'vat_amount', 'line_total_ht', 'line_total_ttc'];
                $drop = array_values(array_filter($cols, fn ($col) => Schema::connection('tenant')->hasColumn('purchase_lines', $col)));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
