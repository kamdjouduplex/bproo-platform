<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotations', 'customer_purchase_order')) {
                $table->string('customer_purchase_order', 120)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('quotations', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotations', 'customer_purchase_order')) {
                $table->dropColumn('customer_purchase_order');
            }
        });
    }
};
