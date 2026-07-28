<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('pressing_clients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('whatsapp');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('whatsapp');
            $table->index('agence_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_clients');
    }
};
