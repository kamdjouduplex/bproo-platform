<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('zones')) {
            return;
        }

        Schema::connection('tenant')->create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('region')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('zones');
    }
};
