<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotations')) {
            return;
        }

        $conn = DB::connection('tenant');

        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement('ALTER TABLE quotations DROP CONSTRAINT IF EXISTS quotations_status_check');
        }

        $conn->table('quotations')->where('status', 'validated')->update(['status' => 'accepted']);

        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement(
                "ALTER TABLE quotations ADD CONSTRAINT quotations_status_check CHECK (status::text = ANY (ARRAY['draft','sent','accepted','suspended','rejected']::text[]))"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('quotations')) {
            return;
        }

        $conn = DB::connection('tenant');

        $conn->table('quotations')->where('status', 'accepted')->update(['status' => 'validated']);

        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement('ALTER TABLE quotations DROP CONSTRAINT IF EXISTS quotations_status_check');
            $conn->statement(
                "ALTER TABLE quotations ADD CONSTRAINT quotations_status_check CHECK (status::text = ANY (ARRAY['draft','sent','validated','suspended','rejected']::text[]))"
            );
        }
    }
};
