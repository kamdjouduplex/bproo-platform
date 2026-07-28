<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();       // bank_transfer | check | cash | mobile_money
            $table->string('reference', 255)->nullable();            // bank ref, check number
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();   // user_id
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('invoice_payments');
    }
};
