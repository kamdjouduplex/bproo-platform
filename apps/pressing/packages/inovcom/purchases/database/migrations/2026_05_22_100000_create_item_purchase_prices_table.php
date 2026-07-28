<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('item_purchase_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('purchase_line_id')->nullable()->constrained('purchase_lines')->nullOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['item_id', 'recorded_at']);
            $table->index(['item_id', 'provider_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('item_purchase_prices');
    }
};
