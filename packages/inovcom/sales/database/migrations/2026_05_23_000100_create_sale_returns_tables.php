<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('sale_returns')) {
            return;
        }

        Schema::connection('tenant')->create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 32)->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('status', 20)->default('confirmed');
            $table->string('type', 20)->default('partial');
            $table->date('return_date');
            $table->decimal('subtotal_refund', 15, 2)->default(0);
            $table->decimal('discount_refund', 15, 2)->default(0);
            $table->decimal('total_refund', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'status']);
            $table->index('return_date');
        });

        Schema::connection('tenant')->create('sale_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_base', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_refund', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('sale_return_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('method', 32);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('sale_return_refunds');
        Schema::connection('tenant')->dropIfExists('sale_return_lines');
        Schema::connection('tenant')->dropIfExists('sale_returns');
    }
};
