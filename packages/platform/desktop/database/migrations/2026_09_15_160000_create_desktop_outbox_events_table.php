<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local desktop outbox (Phase 4). Runs on the stand-alone install DB only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('desktop_outbox_events')) {
            return;
        }

        Schema::create('desktop_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('type', 128)->index();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('payload');
            $table->string('status', 32)->default('pending')->index(); // pending|sent|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_outbox_events');
    }
};
