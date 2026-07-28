<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('article_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('article_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_type_id')->constrained('article_types')->cascadeOnDelete();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['article_type_id', 'agence_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('article_prices');
        Schema::connection('tenant')->dropIfExists('article_types');
    }
};
