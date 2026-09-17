<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local desktop inbox (Phase 5 IN sync / multi-PC).
 * Stores events pulled from Control Center (other installs, same tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('desktop_inbox_events')) {
            return;
        }

        Schema::create('desktop_inbox_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cloud_id')->nullable();
            $table->uuid('event_id')->unique();
            $table->string('type', 128)->index();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('payload');
            $table->uuid('source_install_uuid')->nullable()->index();
            $table->string('status', 32)->default('pending')->index(); // pending|applied|failed|ignored
            $table->timestamp('applied_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['cloud_id']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_inbox_events');
    }
};
