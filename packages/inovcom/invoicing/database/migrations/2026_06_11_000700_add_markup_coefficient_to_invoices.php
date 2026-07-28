<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('invoices', 'show_markup_coefficient')) {
                $table->boolean('show_markup_coefficient')->default(false)->after('payment_mode');
            }
        });

        Schema::connection('tenant')->table('invoice_lines', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('invoice_lines', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
            }
            if (!Schema::connection('tenant')->hasColumn('invoice_lines', 'markup_coefficient')) {
                $table->decimal('markup_coefficient', 10, 4)->nullable()->after('unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('invoice_lines', function (Blueprint $table) {
            foreach (['markup_coefficient', 'unit_cost'] as $col) {
                if (Schema::connection('tenant')->hasColumn('invoice_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('invoices', 'show_markup_coefficient')) {
                $table->dropColumn('show_markup_coefficient');
            }
        });
    }
};
