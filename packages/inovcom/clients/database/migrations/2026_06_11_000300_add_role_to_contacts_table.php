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

        $schema->table('contacts', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('contacts', 'role')) {
                // principal, buyer, accountant, director, technician, other
                $table->string('role', 20)->default('other')->after('position');
            }
            if (! $schema->hasColumn('contacts', 'civility')) {
                $table->string('civility', 10)->nullable()->after('first_name');
            }
            if (! $schema->hasColumn('contacts', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_primary');
            }
        });

        $conn = DB::connection('tenant');
        if ($conn->getDriverName() === 'pgsql') {
            // Un seul contact principal par client.
            try {
                $conn->statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_contacts_primary ON contacts (client_id) WHERE is_primary = true');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('tenant');
        if ($conn->getDriverName() === 'pgsql') {
            $conn->statement('DROP INDEX IF EXISTS uq_contacts_primary');
        }

        $schema = Schema::connection('tenant');
        $schema->table('contacts', function (Blueprint $table) use ($schema) {
            foreach (['role', 'civility', 'is_active'] as $column) {
                if ($schema->hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
