<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->date('paid_at');
            $table->string('method', 50)->default('cash'); // cash, bank_transfer, check, other
            $table->string('reference', 255)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable(); // admin user id (users table in central app if exists)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
