<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('return_items')) {
            return;
        }

        $conn->create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->unsignedBigInteger('source_line_id')->nullable(); // invoice_line_id
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name', 255);
            $table->string('item_sku', 60)->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_discount', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->nullable();
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->string('condition', 20)->nullable();   // resellable|defective|damaged
            $table->boolean('restock')->default(true);
            $table->decimal('restocked_quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->index('return_id');
            $table->index('item_id');
            $table->index('source_line_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('return_items');
    }
};
