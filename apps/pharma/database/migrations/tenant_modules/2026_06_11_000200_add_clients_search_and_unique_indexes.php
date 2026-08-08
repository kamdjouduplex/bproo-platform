<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('tenant');

        if ($conn->getDriverName() !== 'pgsql') {
            return; // Index PostgreSQL spécifiques (pg_trgm, partiels) : pgsql uniquement.
        }

        // Recherche insensible à la casse performante (ILIKE) via pg_trgm.
        // Best-effort : si l'extension n'est pas disponible, ILIKE fonctionne sans index.
        try {
            $conn->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_name_trgm ON clients USING gin (name gin_trgm_ops)');
            $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_code_trgm ON clients USING gin (code gin_trgm_ops)');
        } catch (\Throwable $e) {
            // Extension indisponible : on continue sans index trigram.
        }

        // Unicité métier NIU / RCCM (hors valeurs nulles et clients supprimés).
        // Best-effort : des doublons existants ne doivent pas bloquer le reste du MVP
        // (la validation applicative reste active dans ClientRules).
        try {
            $conn->statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_clients_niu ON clients (niu) WHERE niu IS NOT NULL AND deleted_at IS NULL');
        } catch (\Throwable $e) {
        }
        try {
            $conn->statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_clients_rccm ON clients (rccm) WHERE rccm IS NOT NULL AND deleted_at IS NULL');
        } catch (\Throwable $e) {
        }

        // Index btree de filtrage/liste.
        $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_segment ON clients (segment_id)');
        $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_salesrep ON clients (salesrep_id)');
        $conn->statement('CREATE INDEX IF NOT EXISTS idx_clients_active ON clients (is_active)');
    }

    public function down(): void
    {
        $conn = DB::connection('tenant');

        if ($conn->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'idx_clients_name_trgm',
            'idx_clients_code_trgm',
            'uq_clients_niu',
            'uq_clients_rccm',
            'idx_clients_segment',
            'idx_clients_salesrep',
            'idx_clients_active',
        ] as $index) {
            $conn->statement('DROP INDEX IF EXISTS ' . $index);
        }
    }
};
