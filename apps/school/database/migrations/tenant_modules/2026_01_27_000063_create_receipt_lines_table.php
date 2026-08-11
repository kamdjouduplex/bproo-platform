<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_note_id')->constrained('receipt_notes')->cascadeOnDelete();
            $table->foreignId('purchase_line_id')->constrained('purchase_lines')->cascadeOnDelete();
            $table->decimal('quantity_received', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('receipt_lines');
    }
};
