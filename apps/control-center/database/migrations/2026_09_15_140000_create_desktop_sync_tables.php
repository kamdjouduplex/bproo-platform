<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive Phase 3 desktop OUT sync (cloud backup of append-only events).
 * Does NOT alter SaaS tenant DBs, SubscriptionService, or apply events yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('desktop_sync_batches')) {
            Schema::create('desktop_sync_batches', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->foreignId('desktop_install_id')->constrained('desktop_installs')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('product_key', 32)->nullable();
                $table->unsignedInteger('event_count')->default(0);
                $table->unsignedInteger('accepted_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->string('status', 32)->default('accepted')->index(); // accepted|partial|rejected
                $table->timestamp('received_at')->useCurrent()->index();
                $table->string('client_app_version', 64)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['desktop_install_id', 'uuid']);
                $table->index(['tenant_id', 'received_at']);
            });
        }

        if (! Schema::hasTable('desktop_sync_events')) {
            Schema::create('desktop_sync_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id');
                $table->foreignId('desktop_install_id')->constrained('desktop_installs')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('desktop_sync_batches')->nullOnDelete();
                $table->string('product_key', 32)->nullable()->index();
                $table->string('type', 128)->index(); // sale.created, stock.adjusted, ...
                $table->unsignedSmallInteger('schema_version')->default(1);
                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamp('received_at')->useCurrent()->index();
                $table->json('payload');
                $table->timestamps();

                $table->unique(['desktop_install_id', 'event_id']);
                $table->index(['tenant_id', 'received_at']);
                $table->index(['desktop_install_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_sync_events');
        Schema::dropIfExists('desktop_sync_batches');
    }
};
