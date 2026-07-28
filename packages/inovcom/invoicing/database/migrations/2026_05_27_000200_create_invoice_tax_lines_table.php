<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('invoice_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('tax_name');
            $table->decimal('tax_rate', 5, 2)->nullable()->default(null);
            $table->decimal('tax_amount', 12, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('invoice_tax_lines');
    }
};

