<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('school_payments')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_receipts');
    }
};

