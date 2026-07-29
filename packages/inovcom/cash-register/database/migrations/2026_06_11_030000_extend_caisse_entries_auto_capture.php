<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Étend caisse_entries pour l'auto-capture des mouvements financiers du système :
 *  - source     : origine fonctionnelle du mouvement (sale, invoice, debt, expense, avoir, manual, session)
 *  - is_reversal / reversed_entry_id : contre-passation (annulations)
 *  - index d'idempotence : un seul mouvement par (reference_type, reference_id, entry_type)
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('caisse_entries')) {
            return;
        }

        $schema->table('caisse_entries', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('caisse_entries', 'source')) {
                $table->string('source', 32)->nullable();
            }
            if (! $schema->hasColumn('caisse_entries', 'is_reversal')) {
                $table->boolean('is_reversal')->default(false);
            }
            if (! $schema->hasColumn('caisse_entries', 'reversed_entry_id')) {
                $table->unsignedBigInteger('reversed_entry_id')->nullable();
            }
        });

        DB::connection('tenant')->statement(
            'CREATE INDEX IF NOT EXISTS caisse_entries_source_idx ON caisse_entries (source)'
        );

        DB::connection('tenant')->statement(
            'CREATE INDEX IF NOT EXISTS caisse_entries_reference_idx ON caisse_entries (reference_type, reference_id)'
        );

        // Idempotence : empêche le double-enregistrement d'un même mouvement métier.
        DB::connection('tenant')->statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS caisse_entries_reference_unique '
            . 'ON caisse_entries (reference_type, reference_id, entry_type) '
            . 'WHERE reference_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('caisse_entries')) {
            return;
        }

        DB::connection('tenant')->statement('DROP INDEX IF EXISTS caisse_entries_reference_unique');
        DB::connection('tenant')->statement('DROP INDEX IF EXISTS caisse_entries_reference_idx');
        DB::connection('tenant')->statement('DROP INDEX IF EXISTS caisse_entries_source_idx');

        $schema->table('caisse_entries', function (Blueprint $table) use ($schema) {
            foreach (['source', 'is_reversal', 'reversed_entry_id'] as $col) {
                if ($schema->hasColumn('caisse_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
