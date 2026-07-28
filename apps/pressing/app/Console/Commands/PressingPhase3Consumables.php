<?php

namespace App\Console\Commands;

use App\Jobs\InstallModuleJob;
use App\Models\Module;
use App\Services\ModulesCatalogSync;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Pressing\Modules\PressingConsumablesModule;
use Pressing\Services\PressingConsumablesService;

class PressingPhase3Consumables extends Command
{
    protected $signature = 'pressing:phase3-consumables {tenantCode=pressing}';

    protected $description = 'Phase 3: enable items/stock (+purchases/inventory) and seed pressing consumables.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        ModulesCatalogSync::syncFromConfig(preserveDefaults: true);

        $deps = ['items', 'stock', 'providers', 'purchases', 'inventory', 'pressing_consumables'];

        foreach ($deps as $key) {
            $this->info("Installing module: {$key}");
            try {
                (new InstallModuleJob($tenant->id, $key))->handle(app(\App\Services\ModuleRegistry::class));
            } catch (\Throwable $e) {
                $this->warn("  → {$key}: ".$e->getMessage());
            }
        }

        $manager->setTenant($tenant);

        // Ensure consumables catalogue even if module install partially failed
        if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('items')
            && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('stock_levels')) {
            (new PressingConsumablesModule)->install($tenant);
            $count = count(app(PressingConsumablesService::class)->dashboardRows());
            $this->info("Catalogue consommables prêt ({$count} articles).");
        } else {
            $this->error('Tables items/stock indisponibles — vérifiez l’installation des modules.');

            return self::FAILURE;
        }

        $module = Module::where('key', 'pressing_consumables')->first();
        if ($module) {
            $tenant->modules()->syncWithoutDetaching([$module->id => ['enabled' => true]]);
        }

        $this->info('Phase 3 consommables OK pour '.$tenant->code.'.');

        return self::SUCCESS;
    }
}
