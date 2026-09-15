<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Phase 2 desktop update channel.
 * Does NOT alter subscriptions / desktop_installs SaaS behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('desktop_releases')) {
            return;
        }

        Schema::create('desktop_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('product_key', 32)->index(); // pharma|erp|school|...
            $table->string('channel', 32)->default('stable')->index(); // stable|beta
            $table->string('version', 64); // semver e.g. 1.2.3
            $table->string('status', 32)->default('draft')->index(); // draft|published|yanked
            $table->text('changelog')->nullable();
            $table->string('package_disk', 32)->nullable(); // desktop_updates|s3|...
            $table->string('package_path')->nullable(); // relative path on disk
            $table->string('package_url')->nullable(); // external CDN URL when not stored locally
            $table->string('package_sha256', 64)->nullable();
            $table->unsignedBigInteger('package_size')->nullable();
            $table->string('min_version', 64)->nullable(); // desktop must be >= this to apply
            $table->boolean('mandatory')->default(false);
            $table->json('meta')->nullable(); // apply hints, healthcheck, etc.
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('yanked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_key', 'channel', 'version']);
            $table->index(['product_key', 'channel', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_releases');
    }
};
