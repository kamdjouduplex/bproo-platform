<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('tenant');
        if ($conn->hasTable('credit_note_items')) {
            return;
        }

        $conn->create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->unsignedBigInteger('return_item_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name', 255);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->nullable();
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index('credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('credit_note_items');
    }
};
