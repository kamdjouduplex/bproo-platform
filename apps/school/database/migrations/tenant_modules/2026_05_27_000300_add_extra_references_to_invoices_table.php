<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            $table->string('quotation_reference')->nullable()->after('customer_reference');
            $table->text('additional_info')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            $table->dropColumn(['quotation_reference', 'additional_info']);
        });
    }
};

