<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ex: "Comptant", "Net 30", "Net 60"
            $table->integer('days')->default(0); // Number of days for payment
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payment_terms');
    }
};
