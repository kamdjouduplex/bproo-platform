<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('agences', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('location')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('manager_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('manager_user_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('agences');
    }
};
