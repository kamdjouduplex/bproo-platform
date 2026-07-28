<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('type', 50)->default('other');
            // visit_terrain | reunion | maintenance | project_milestone | other
            $table->string('status', 50)->default('scheduled');
            // scheduled | confirmed | done | cancelled
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('location', 500)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->timestamps();

            $table->index(['start_at', 'end_at']);
            $table->index('assigned_to');
            $table->index('client_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_appointments');
    }
};
