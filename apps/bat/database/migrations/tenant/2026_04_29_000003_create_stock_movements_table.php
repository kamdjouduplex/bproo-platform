<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->decimal('quantity', 12, 3); // positive = stock in, negative = stock out
            $table->string('type');             // IN | OUT | TRANSFER
            $table->string('reference_type')->nullable(); // project, delivery, purchase, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            $table->index(['product_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
