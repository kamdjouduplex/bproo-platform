<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('delivery_notes')) {
            return;
        }

        Schema::connection('tenant')->create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('delivery_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index('delivery_date');
        });

        Schema::connection('tenant')->create('delivery_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained('invoice_lines')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('item_name');
            $table->string('item_sku')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->index(['delivery_note_id', 'invoice_line_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('delivery_note_lines');
        Schema::connection('tenant')->dropIfExists('delivery_notes');
    }
};
