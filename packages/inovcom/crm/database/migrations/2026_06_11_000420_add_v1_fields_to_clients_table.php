<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('clients', 'zone_id')) {
                $table->unsignedBigInteger('zone_id')->nullable()->after('segment_id');
            }
            if (! $schema->hasColumn('clients', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('zone_id');
            }
            if (! $schema->hasColumn('clients', 'discount_rate')) {
                $table->decimal('discount_rate', 5, 2)->default(0)->after('credit_limit');
            }
            if (! $schema->hasColumn('clients', 'price_tier')) {
                $table->string('price_tier', 20)->default('retail')->after('discount_rate');
            }
            // Blocage commercial
            if (! $schema->hasColumn('clients', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('is_active');
            }
            if (! $schema->hasColumn('clients', 'block_reason')) {
                $table->string('block_reason')->nullable()->after('is_blocked');
            }
            if (! $schema->hasColumn('clients', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('block_reason');
            }
        });

        // FK (tables appartenant au module Clients : contrainte sûre)
        if ($schema->hasTable('zones')) {
            try {
                $schema->table('clients', function (Blueprint $table) {
                    $table->foreign('zone_id')->references('id')->on('zones')->nullOnDelete();
                });
            } catch (\Throwable $e) {
            }
        }
        if ($schema->hasTable('client_categories')) {
            try {
                $schema->table('clients', function (Blueprint $table) {
                    $table->foreign('category_id')->references('id')->on('client_categories')->nullOnDelete();
                });
            } catch (\Throwable $e) {
            }
        }

        $conn = DB::connection('tenant');
        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_zone ON clients (zone_id)');
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_category ON clients (category_id)');
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_blocked ON clients (is_blocked)');
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        $schema->table('clients', function (Blueprint $table) use ($schema) {
            foreach (['zone_id', 'category_id'] as $fk) {
                try {
                    $table->dropForeign([$fk]);
                } catch (\Throwable $e) {
                }
            }
            foreach (['zone_id', 'category_id', 'discount_rate', 'price_tier', 'is_blocked', 'block_reason', 'blocked_at'] as $column) {
                if ($schema->hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
