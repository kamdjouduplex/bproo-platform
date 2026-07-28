<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('expected_quantity', 12, 3)->default(0); // From stock_levels at start
            $table->decimal('counted_quantity', 12, 3)->nullable(); // Actual count
            $table->decimal('difference', 12, 3)->default(0); // counted - expected
            $table->decimal('value_difference', 12, 2)->default(0); // Monetary value of difference
            $table->text('notes')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            
            $table->index('stock_count_id');
            $table->index('item_id');
            $table->unique(['stock_count_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('stock_count_lines');
    }
};
