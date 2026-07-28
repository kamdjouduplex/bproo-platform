<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('type'); // in, out, adjustment, transfer
            $table->string('reference_type')->nullable(); // Sale, Purchase, Adjustment, etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of related record
            $table->decimal('quantity', 12, 3); // Positive for in, negative for out
            $table->decimal('quantity_before', 12, 3); // Stock before movement
            $table->decimal('quantity_after', 12, 3); // Stock after movement
            $table->text('reason')->nullable(); // Reason for movement
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('item_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('stock_movements');
    }
};
