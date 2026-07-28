<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('provider_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('name'); // Contact person name
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('position')->nullable(); // Ex: "Responsable des ventes"
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('provider_contacts');
    }
};
