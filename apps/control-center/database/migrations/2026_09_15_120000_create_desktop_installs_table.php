<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Phase 1 offline licence plane.
 * Does NOT alter subscriptions / payments / SaaS grace_ends_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('desktop_installs')) {
            return;
        }

        Schema::create('desktop_installs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('product_key', 32)->default('pharma');
            $table->string('label')->nullable();
            $table->string('activation_code', 64)->unique();
            $table->string('fingerprint_hash', 128)->nullable()->index();
            $table->string('status', 32)->default('pending')->index(); // pending|active|revoked
            $table->unsignedInteger('token_version')->default(1);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('app_version', 64)->nullable();
            $table->string('os', 64)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'fingerprint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_installs');
    }
};
