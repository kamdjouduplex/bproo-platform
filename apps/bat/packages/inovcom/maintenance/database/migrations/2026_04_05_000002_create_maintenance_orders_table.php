<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('type', 50)->default('corrective'); // corrective | preventive | emergency
            $table->string('priority', 20)->default('normal'); // low | normal | high | critical
            $table->string('status', 50)->default('open');     // open | assigned | in_progress | done | closed | cancelled
            $table->text('site_address')->nullable();
            $table->string('reported_by', 255)->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('due_at')->nullable();           // SLA deadline
            $table->unsignedBigInteger('assigned_to')->nullable();
            // Workflow timestamps
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('maintenance_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_orders');
    }
};
