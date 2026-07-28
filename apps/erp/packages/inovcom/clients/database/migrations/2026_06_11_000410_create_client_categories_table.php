<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('client_categories')) {
            return;
        }

        Schema::connection('tenant')->create('client_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->decimal('default_discount_rate', 5, 2)->default(0);
            $table->string('default_price_tier', 20)->default('retail');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('client_categories');
    }
};
