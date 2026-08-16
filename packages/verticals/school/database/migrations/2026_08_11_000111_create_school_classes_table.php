<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ex: 1ère année, 2nde, etc.
            $table->string('section')->nullable(); // optional subdivision
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_classes');
    }
};

