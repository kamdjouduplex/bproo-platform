<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_payments', 'withholding_total')) {
                $table->decimal('withholding_total', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('invoice_payments', 'settled_amount')) {
                $table->decimal('settled_amount', 15, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn(['withholding_total', 'settled_amount']);
        });
    }
};
