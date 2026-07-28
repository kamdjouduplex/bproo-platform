<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('planning_participants')) {
            return;
        }

        Schema::connection('tenant')->create('planning_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->unsignedBigInteger('user_id')->nullable(); // internal user (no FK — tenant isolation)
            $table->string('name', 255)->nullable();           // external OR auto-filled display name
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_external')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('planning_participants');
    }
};
