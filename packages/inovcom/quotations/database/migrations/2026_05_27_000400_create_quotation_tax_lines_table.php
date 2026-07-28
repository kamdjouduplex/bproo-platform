<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('quotation_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('tax_name');
            $table->string('tax_mode', 10)->default('percent'); // percent|amount
            $table->decimal('tax_rate', 7, 3)->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('quotation_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('quotation_tax_lines');
    }
};

