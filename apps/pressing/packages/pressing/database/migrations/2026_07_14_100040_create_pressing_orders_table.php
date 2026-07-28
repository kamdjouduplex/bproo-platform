<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('pressing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('pressing_clients')->restrictOnDelete();
            $table->unsignedBigInteger('receptionist_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('due_at')->nullable();
            $table->foreignId('current_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->string('status')->default('open'); // open, ready, delivered, cancelled
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('qr_token')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agence_id', 'received_at']);
            $table->index(['client_id', 'status']);
            $table->index('current_stage_id');
            $table->index('receptionist_id');
        });

        Schema::connection('tenant')->create('pressing_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pressing_orders')->cascadeOnDelete();
            $table->foreignId('article_type_id')->constrained('article_types')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('color')->nullable();
            $table->string('brand')->nullable();
            $table->string('size')->nullable();
            $table->text('notes')->nullable();
            $table->string('condition_on_receipt')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_order_items');
        Schema::connection('tenant')->dropIfExists('pressing_orders');
    }
};
