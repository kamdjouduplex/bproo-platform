<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->unsignedBigInteger('client_id');
            $table->string('title', 255)->nullable();
            $table->string('type', 50)->default('full_service'); // preventive | corrective | full_service
            $table->string('status', 50)->default('active');     // active | suspended | expired
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('price_per_month', 15, 2)->nullable();
            $table->unsignedInteger('response_time')->nullable();   // SLA hours
            $table->unsignedInteger('resolution_time')->nullable(); // SLA hours
            $table->string('billing_cycle', 20)->default('monthly'); // monthly | quarterly | yearly
            $table->jsonb('sites')->nullable();   // [{label, address}]
            $table->text('terms')->nullable();
            // Workflow timestamps
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_contracts');
    }
};
