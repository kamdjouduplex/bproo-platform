<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique(); // optional human code (e.g. 2026-2027)
            $table->string('name'); // e.g. Année académique 2026-2027
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('academic_years');
    }
};

