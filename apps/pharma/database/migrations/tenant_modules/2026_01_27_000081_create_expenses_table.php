<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->enum('payment_method', ['cash', 'check', 'bank_transfer', 'mobile_money', 'other'])->default('cash');
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'paid'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable(); // For attachments, receipts, etc.
            $table->timestamps();
            
            $table->index('reference');
            $table->index('expense_category_id');
            $table->index('expense_date');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('expenses');
    }
};
