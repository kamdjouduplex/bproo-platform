<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('pressing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pressing_orders')->cascadeOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->restrictOnDelete();
            $table->string('type')->default('agence'); // agence, domicile
            $table->string('status')->default('pending'); // pending, in_transit, delivered, cancelled
            $table->unsignedBigInteger('driver_user_id')->nullable();
            $table->string('vehicle')->nullable();
            $table->string('address')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('driver_user_id');
        });

        Schema::connection('tenant')->create('pressing_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::connection('tenant')->create('pressing_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->string('channel'); // whatsapp, sms, email, in_app
            $table->string('status'); // queued, sent, failed, skipped
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('recipient')->nullable();
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('pressing_notification_logs');
        Schema::connection('tenant')->dropIfExists('pressing_notifications');
        Schema::connection('tenant')->dropIfExists('pressing_deliveries');
    }
};
