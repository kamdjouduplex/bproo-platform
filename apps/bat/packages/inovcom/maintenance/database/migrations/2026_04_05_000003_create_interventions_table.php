<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->unsignedBigInteger('technician_id');
            $table->string('status', 50)->default('scheduled'); // scheduled | in_progress | done
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('work_done')->nullable();
            $table->text('findings')->nullable();
            $table->text('next_action')->nullable();
            $table->text('client_signature')->nullable(); // base64 image
            $table->timestamp('signed_at')->nullable();
            $table->jsonb('photos')->nullable();           // [{path, caption}]
            $table->jsonb('materials_used')->nullable();   // [{item_id, qty, unit_price}]
            $table->timestamps();

            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
