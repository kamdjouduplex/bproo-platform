<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('payroll_line_items')) {
            return;
        }

        Schema::connection('tenant')->create('payroll_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained('payroll_lines')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('label', 255);
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payroll_line_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_line_items');
    }
};
