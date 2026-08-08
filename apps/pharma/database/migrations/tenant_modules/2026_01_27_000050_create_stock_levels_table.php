<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0); // Quantity on hand
            $table->decimal('reserved_quantity', 12, 3)->default(0); // Reserved for pending orders
            $table->decimal('available_quantity', 12, 3)->default(0); // Available = quantity - reserved
            $table->decimal('reorder_point', 12, 3)->nullable(); // Alert when stock is low
            $table->decimal('max_stock', 12, 3)->nullable(); // Maximum stock level
            $table->timestamps();
            
            // Index for quick lookups
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('stock_levels');
    }
};
