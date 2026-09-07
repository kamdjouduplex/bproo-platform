<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_payment_attachments') || ! Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::create('invoice_payment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('original_name')->nullable();
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_attachments');
    }
};
