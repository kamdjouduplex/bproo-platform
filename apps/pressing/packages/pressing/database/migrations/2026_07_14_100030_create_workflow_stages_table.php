<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#64748b');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->index(['agence_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('workflow_stages');
    }
};
