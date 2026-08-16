<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy bootstrap marker kept for module install/publish compatibility.
        // School schema lives in later tenant_modules migrations.
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

