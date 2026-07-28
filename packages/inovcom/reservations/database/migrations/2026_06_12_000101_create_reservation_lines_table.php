<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('reservation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_name');
            $table->string('item_sku')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('quantity_cancelled', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['reservation_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('reservation_lines');
    }
};
