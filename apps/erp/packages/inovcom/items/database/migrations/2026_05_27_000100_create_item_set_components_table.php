<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('item_set_components')) {
            return;
        }

        Schema::connection('tenant')->create('item_set_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['set_item_id', 'component_item_id'], 'item_set_components_unique');
            $table->index('set_item_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('item_set_components');
    }
};
