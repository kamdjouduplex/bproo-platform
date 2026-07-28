<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('refunds')) {
            return;
        }

        $conn->create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 32)->unique();    // RB-2026-000001
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('method', 20);                    // cash|bank_transfer|mobile_money|check|customer_credit
            $table->decimal('amount', 15, 2);
            $table->date('refund_date');
            $table->unsignedBigInteger('caisse_entry_id')->nullable();
            $table->unsignedBigInteger('invoice_payment_id')->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('credit_note_id');
            $table->index('refund_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('refunds');
    }
};
