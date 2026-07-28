<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['loss', 'gain', 'adjustment'])->default('adjustment');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('code');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('reasons');
    }
};
