<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('item_unit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('conversion_factor', 12, 4)->default(1); // 1 unit = X base units (e.g. 1 carton = 15 pieces)
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->boolean('is_default')->default(false); // Default unit for display
            $table->timestamps();

            $table->unique(['item_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('item_unit_prices');
    }
};
