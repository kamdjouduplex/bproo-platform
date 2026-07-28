<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('receipt_notes', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique(); // Ex: REC-2026-000001
            $table->date('receipt_date');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('status')->default('partial'); // partial, complete
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('receipt_notes');
    }
};
