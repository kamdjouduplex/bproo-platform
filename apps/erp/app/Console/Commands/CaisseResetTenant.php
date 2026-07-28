<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Caisse\Services\CaisseService;

/**
 * Réinitialise les données de caisse d'un tenant (sessions + écritures), puis ré-initialise
 * un registre vierge. Les liens refunds.caisse_entry_id sont détachés au préalable.
 */
class CaisseResetTenant extends Command
{
    protected $signature = 'caisse:reset-tenant {tenantCode} {--force : Ne pas demander de confirmation}';

    protected $description = 'Vide les données de caisse (sessions + écritures) d\'un tenant et ré-initialise le registre.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant introuvable : ' . $this->argument('tenantCode'));

            return self::FAILURE;
        }

        $manager->setTenant($tenant);

        if (! Schema::connection('tenant')->hasTable('caisse_entries')
            || ! Schema::connection('tenant')->hasTable('caisse_sessions')) {
            $this->error('Les tables de caisse n\'existent pas pour ce tenant. Lancez d\'abord les migrations.');

            return self::FAILURE;
        }

        $entriesCount = DB::connection('tenant')->table('caisse_entries')->count();
        $sessionsCount = DB::connection('tenant')->table('caisse_sessions')->count();

        $this->warn(sprintf(
            'Cette action va SUPPRIMER %d écriture(s) et %d session(s) de caisse pour le tenant "%s".',
            $entriesCount,
            $sessionsCount,
            $tenant->code
        ));

        if (! $this->option('force') && ! $this->confirm('Confirmer la réinitialisation de la caisse ?')) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        DB::connection('tenant')->transaction(function () {
            // Détacher les liens des remboursements vers les écritures de caisse.
            if (Schema::connection('tenant')->hasTable('refunds')
                && Schema::connection('tenant')->hasColumn('refunds', 'caisse_entry_id')) {
                DB::connection('tenant')->table('refunds')
                    ->whereNotNull('caisse_entry_id')
                    ->update(['caisse_entry_id' => null]);
            }

            DB::connection('tenant')->table('caisse_entries')->delete();
            DB::connection('tenant')->table('caisse_sessions')->delete();

            // Réinitialiser les séquences d'identité (PostgreSQL).
            foreach (['caisse_entries', 'caisse_sessions'] as $table) {
                try {
                    DB::connection('tenant')->statement(
                        'ALTER SEQUENCE ' . $table . '_id_seq RESTART WITH 1'
                    );
                } catch (\Throwable $e) {
                    // Séquence absente ou non standard : sans incidence fonctionnelle.
                }
            }
        });

        app(CaisseService::class)->ensureLedgerInitialized();

        $this->info('Caisse réinitialisée pour ' . $tenant->code . '. Registre vierge prêt (solde 0).');

        return self::SUCCESS;
    }
}
