<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('delivery_notes', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'customer_purchase_order')) {
                $table->string('customer_purchase_order', 120)->nullable()->after('notes');
            }
            if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'show_prices')) {
                $table->boolean('show_prices')->default(false)->after('customer_purchase_order');
            }
            if (!Schema::connection('tenant')->hasColumn('delivery_notes', 'show_discounts')) {
                $table->boolean('show_discounts')->default(false)->after('show_prices');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('delivery_notes', function (Blueprint $table) {
            foreach (['show_discounts', 'show_prices', 'customer_purchase_order'] as $col) {
                if (Schema::connection('tenant')->hasColumn('delivery_notes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
