<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('allow_operations')->default(true); // Allow operations while counting
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('reference');
            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('stock_counts');
    }
};
