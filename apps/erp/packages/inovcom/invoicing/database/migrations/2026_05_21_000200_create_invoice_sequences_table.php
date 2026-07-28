<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->enum('declaration_type', ['declared', 'non_declared']);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['declaration_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('invoice_sequences');
    }
};
