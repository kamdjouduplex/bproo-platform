<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            $table->string('customer_reference')->nullable()->after('quotation_id');
            $table->string('delivery_note_number')->nullable()->after('customer_reference');
            $table->string('payment_mode')->nullable()->after('notes');
        });

        Schema::connection('tenant')->table('invoice_lines', function (Blueprint $table) {
            $table->decimal('line_discount', 12, 2)->default(0)->after('unit_price');
            $table->decimal('unit_price_net', 12, 2)->nullable()->after('line_discount');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['line_discount', 'unit_price_net']);
        });

        Schema::connection('tenant')->table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_reference', 'delivery_note_number', 'payment_mode']);
        });
    }
};
