<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('item_foreign_purchase_prices')) {
            return;
        }

        Schema::connection('tenant')->create('item_foreign_purchase_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->unsignedBigInteger('foreign_purchase_order_id')->nullable();
            $table->unsignedBigInteger('foreign_purchase_line_id')->nullable();
            $table->string('currency_code', 10);
            $table->decimal('unit_price_foreign', 15, 4);
            $table->decimal('unit_price_local', 15, 2)->nullable();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['item_id', 'currency_code', 'recorded_at']);
            $table->index(['item_id', 'provider_id', 'currency_code', 'recorded_at']);

            if (Schema::connection('tenant')->hasTable('foreign_purchase_orders')) {
                $table->foreign('foreign_purchase_order_id')
                    ->references('id')
                    ->on('foreign_purchase_orders')
                    ->nullOnDelete();
            }

            if (Schema::connection('tenant')->hasTable('foreign_purchase_lines')) {
                $table->foreign('foreign_purchase_line_id')
                    ->references('id')
                    ->on('foreign_purchase_lines')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('item_foreign_purchase_prices');
    }
};
