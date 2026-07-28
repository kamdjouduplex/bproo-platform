<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return;
        }

        $conn = DB::connection('tenant');

        // PostgreSQL : contrainte CHECK créée par enum() Laravel
        $conn->statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');

        $conn->statement(
            "ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status::text = ANY (ARRAY['draft','issued','partial','paid','cancelled','superseded']::text[]))"
        );
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return;
        }

        $conn = DB::connection('tenant');

        $conn->statement("UPDATE invoices SET status = 'cancelled' WHERE status = 'superseded'");

        $conn->statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');

        $conn->statement(
            "ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status::text = ANY (ARRAY['draft','issued','partial','paid','cancelled']::text[]))"
        );
    }
};
