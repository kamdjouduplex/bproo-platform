<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Console\Command;
use School\Support\SchoolRoleCatalog;

class SeedDemoSchoolData extends Command
{
    protected $signature = 'tenant:seed-school-demo {tenantCode=school}';

    protected $description = 'Peuple un tenant école avec données démo (années, classes, élèves, frais, rôles).';

    public function handle(): int
    {
        $tenantCode = $this->argument('tenantCode');
        $manager = app(TenantManager::class);
        $tenant = $manager->resolveByCode($tenantCode);

        if (! $tenant) {
            $this->error("Tenant introuvable : {$tenantCode}");

            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        $this->info("Peuplement démo école pour « {$tenant->name} » ({$tenantCode})…");

        SchoolRoleCatalog::sync();
        (new DemoSchoolSeeder)->run();

        $this->newLine();
        $this->info('✓ Année 2025-2026 active');
        $this->info('✓ 3 classes, 4 matières, 1 enseignant');
        $this->info('✓ 6 élèves inscrits + frais au ledger + lot cartes DEMO-2025');
        $this->info('✓ 1 paiement onsite validé (Amina Diallo)');
        $this->newLine();
        $this->comment('Comptes de test (mot de passe) :');
        $this->line('  directeur.demo@school.test  / Directeur#2025');
        $this->line('  enseignant.demo@school.test / Enseignant#2025');
        $this->line('  caissier.demo@school.test   / Caissier#2025');

        return self::SUCCESS;
    }
}
