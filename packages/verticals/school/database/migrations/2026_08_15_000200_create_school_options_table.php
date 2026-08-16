<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_options', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 60); // section | gender | enrollment_status
            $table->string('value', 120);
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_key', 'value']);
            $table->index(['group_key', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('school_options');
    }
};
