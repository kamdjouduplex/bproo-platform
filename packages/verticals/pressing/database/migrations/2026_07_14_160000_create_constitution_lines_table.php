<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('pressing_order_constitution_lines')) {
            Schema::connection('tenant')->create('pressing_order_constitution_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('pressing_orders')->cascadeOnDelete();
                $table->foreignId('article_type_id')->constrained('article_types')->restrictOnDelete();
                $table->string('color')->nullable();
                $table->string('pattern')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['order_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_order_constitution_lines');
    }
};
