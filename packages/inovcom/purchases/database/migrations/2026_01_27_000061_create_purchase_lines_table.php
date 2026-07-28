<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_name'); // Snapshot
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 15, 2); // quantity * unit_price
            $table->decimal('received_quantity', 10, 3)->default(0); // Quantité reçue
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('purchase_lines');
    }
};
