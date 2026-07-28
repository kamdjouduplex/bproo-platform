<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('stock_count_id')->nullable()->constrained('stock_counts')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('reason_id')->nullable()->constrained('reasons')->nullOnDelete();
            $table->decimal('quantity', 12, 3); // Positive for gain, negative for loss
            $table->decimal('value', 12, 2)->default(0); // Monetary value
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'applied', 'cancelled'])->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            
            $table->index('reference');
            $table->index('stock_count_id');
            $table->index('item_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('adjustments');
    }
};
