<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // Ex: ACH-2026-000001
            $table->date('order_date');
            $table->date('expected_date')->nullable(); // Date de livraison prévue
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft, sent, received, cancelled
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('purchase_orders');
    }
};
