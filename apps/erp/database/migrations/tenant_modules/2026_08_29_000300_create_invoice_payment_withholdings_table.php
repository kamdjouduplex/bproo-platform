<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_payment_withholdings')) {
            return;
        }

        Schema::create('invoice_payment_withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->cascadeOnDelete();
            $table->foreignId('withholding_type_id')->nullable()->constrained('fiscal_withholding_types')->nullOnDelete();
            $table->string('type_code', 50)->nullable();
            $table->string('type_name');
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('rate', 8, 4)->default(0);
            $table->decimal('amount', 15, 2);
            $table->string('account_code', 50)->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_withholdings');
    }
};
