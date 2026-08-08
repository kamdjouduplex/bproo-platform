<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('storage_locations')) {
            return;
        }

        Schema::connection('tenant')->create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('zone', 80);
            $table->string('aisle', 40)->nullable();
            $table->string('shelf', 40)->nullable();
            $table->string('bin', 40)->nullable();
            $table->string('code', 80);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
            $table->unique(['store_id', 'code']);
        });

        Schema::connection('tenant')->create('item_storage_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('storage_location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->unique(['item_id', 'storage_location_id']);
            $table->index(['item_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('item_storage_locations');
        Schema::connection('tenant')->dropIfExists('storage_locations');
    }
};
