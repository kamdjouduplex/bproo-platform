<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'check', 'bank_transfer', 'mobile_money', 'other'])->default('cash');
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('reference');
            $table->index('debt_id');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('debt_payments');
    }
};
