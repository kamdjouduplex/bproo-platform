<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index recherche N° demande achat (invoices.customer_reference).
 * Copie publiée aussi dans database/migrations/tenant_modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('invoices')
            || ! Schema::connection('tenant')->hasColumn('invoices', 'customer_reference')) {
            return;
        }

        $conn = DB::connection('tenant');

        if ($conn->getDriverName() === 'pgsql') {
            try {
                $conn->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                $conn->statement('CREATE INDEX IF NOT EXISTS idx_invoices_customer_reference_trgm ON invoices USING gin (customer_reference gin_trgm_ops)');
            } catch (\Throwable) {
            }
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_invoices_customer_reference ON invoices (customer_reference)');

            return;
        }

        try {
            Schema::connection('tenant')->table('invoices', function ($table) {
                $table->index('customer_reference', 'idx_invoices_customer_reference');
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        $conn = DB::connection('tenant');

        if ($conn->getDriverName() === 'pgsql') {
            foreach (['idx_invoices_customer_reference_trgm', 'idx_invoices_customer_reference'] as $index) {
                try {
                    $conn->statement('DROP INDEX IF EXISTS ' . $index);
                } catch (\Throwable) {
                }
            }

            return;
        }

        try {
            Schema::connection('tenant')->table('invoices', function ($table) {
                $table->dropIndex('idx_invoices_customer_reference');
            });
        } catch (\Throwable) {
        }
    }
};
