<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index recherche N° demande achat (quotations.customer_purchase_order).
 * Copie publiée aussi dans database/migrations/tenant_modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('quotations')
            || ! Schema::connection('tenant')->hasColumn('quotations', 'customer_purchase_order')) {
            return;
        }

        $conn = DB::connection('tenant');

        if ($conn->getDriverName() === 'pgsql') {
            try {
                $conn->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                $conn->statement('CREATE INDEX IF NOT EXISTS idx_quotations_customer_po_trgm ON quotations USING gin (customer_purchase_order gin_trgm_ops)');
            } catch (\Throwable) {
            }
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_quotations_customer_po ON quotations (customer_purchase_order)');

            return;
        }

        try {
            Schema::connection('tenant')->table('quotations', function ($table) {
                $table->index('customer_purchase_order', 'idx_quotations_customer_po');
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        $conn = DB::connection('tenant');

        if ($conn->getDriverName() === 'pgsql') {
            foreach (['idx_quotations_customer_po_trgm', 'idx_quotations_customer_po'] as $index) {
                try {
                    $conn->statement('DROP INDEX IF EXISTS ' . $index);
                } catch (\Throwable) {
                }
            }

            return;
        }

        try {
            Schema::connection('tenant')->table('quotations', function ($table) {
                $table->dropIndex('idx_quotations_customer_po');
            });
        } catch (\Throwable) {
        }
    }
};
