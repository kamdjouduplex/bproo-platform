<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Database\Seeders\DemoHeavyMachinerySeeder;
use Illuminate\Console\Command;

class SeedDemoSampleData extends Command
{
    protected $signature = 'tenant:seed-sample-data {tenantCode=demo}';
    protected $description = 'Peuple le tenant avec des données de démo (pièces auto / engins lourds)';

    public function handle(): int
    {
        $tenantCode = $this->argument('tenantCode');
        $manager = app(TenantManager::class);
        $tenant = $manager->resolveByCode($tenantCode);

        if (!$tenant) {
            $this->error("Tenant introuvable : {$tenantCode}");
            return self::FAILURE;
        }

        $tenant->setSetting('shop_name', 'Demo Pièces Engins Lourds');
        $tenant->update(['name' => 'Demo Pièces Engins Lourds']);

        $manager->setTenant($tenant);

        $this->info("Peuplement des données pour « {$tenant->name} »…");

        (new DemoHeavyMachinerySeeder())->run();

        $this->info('✓ 10 articles pièces détachées (moteur diesel / engins lourds)');
        $this->info('✓ 3 clients (transport, BTP, garage mécanique)');
        $this->info('✓ 3 fournisseurs (Europe, local, distributeur CAT)');
        $this->line('  Catégories : Pièces moteur, Transmission & freinage');
        $this->line('  Marques : CAT, Cummins, OEM');

        return self::SUCCESS;
    }
}
