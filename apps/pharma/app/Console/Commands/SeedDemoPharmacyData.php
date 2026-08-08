<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Database\Seeders\DemoPharmacySeeder;
use Illuminate\Console\Command;

class SeedDemoPharmacyData extends Command
{
    protected $signature = 'tenant:seed-pharmacy-demo {tenantCode=pharma}';

    protected $description = 'Peuple le tenant pharmacie avec des cas de démo (lots périmés, vente OK, Rx, caisse…)';

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

        $this->info("Peuplement démo pharmacie pour « {$tenant->name} » ({$tenantCode})…");

        (new DemoPharmacySeeder)->run();

        $this->newLine();
        $this->info('✓ Catalogue (7 produits) :');
        $this->line('  • Doliprane 500 mg     → 100 % périmé (vente bloquée) ★');
        $this->line('  • Amoxicilline 500 mg  → mixte périmé + valide (+ Rx)');
        $this->line('  • Vitamine C 1000 mg   → bientôt périmé (alerte 30 j)');
        $this->line('  • Efferalgan 500 mg    → stock sain (vente normale)');
        $this->line('  • Augmentin 1 g        → sur ordonnance, stock OK');
        $this->line('  • Gel hydroalcoolique  → sans suivi de lot');
        $this->line('  • Ibuprofène 400 mg    → stock bas');
        $this->info('✓ 3 clients + 1 fournisseur');
        $this->info('✓ Session caisse ouverte (si module actif)');
        $this->info('✓ Historique ventes ~30 jours (graphique + CA mois / jour)');
        $this->newLine();
        $this->comment('Scénario vidéo suggéré :');
        $this->line('  1. Tableau de bord → CA, courbe 7 jours, alertes lots');
        $this->line('  2. Vente → Doliprane → message « stock est périmé »');
        $this->line('  3. Vente → Efferalgan (OK) + cliente Aïcha');
        $this->line('  4. Lots → montrer périmés / alertes');
        $this->line('  5. Titre : « Un logiciel idéal pour la gestion des pharmacies — gratuit »');

        return self::SUCCESS;
    }
}
