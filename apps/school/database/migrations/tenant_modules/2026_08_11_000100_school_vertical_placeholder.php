<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Placeholder: V1 school-specific migrations will land incrementally.
        // The goal is to keep vertical bootstrapping working with module install/publish flow.
        if (! Schema::connection('tenant')->hasTable('school_placeholder')) {
            Schema::connection('tenant')->create('school_placeholder', function (Blueprint $table) {
                $table->id();
                $table->string('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_placeholder');
    }
};

