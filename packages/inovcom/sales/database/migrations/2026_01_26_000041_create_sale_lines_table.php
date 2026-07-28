<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_name'); // Snapshot of item name at time of sale
            $table->string('item_sku')->nullable();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 12, 2); // Price at time of sale
            $table->decimal('line_total', 15, 2); // quantity * unit_price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('sale_lines');
    }
};
