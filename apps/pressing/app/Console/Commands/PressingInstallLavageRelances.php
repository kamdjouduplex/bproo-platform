<?php

namespace App\Console\Commands;

use App\Jobs\InstallModuleJob;
use App\Models\Module;
use App\Services\ModuleRegistry;
use App\Services\ModulesCatalogSync;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Pressing\Modules\PressingLavageRelancesModule;

class PressingInstallLavageRelances extends Command
{
    protected $signature = 'pressing:install-lavage-relances {tenantCode=pressing}';

    protected $description = 'Enable and install the pressing “Relances dépôt lavage” module for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        ModulesCatalogSync::syncFromConfig(preserveDefaults: true);

        try {
            (new InstallModuleJob($tenant->id, 'pressing_lavage_relances'))->handle(app(ModuleRegistry::class));
        } catch (\Throwable $e) {
            $this->warn('  → pressing_lavage_relances: '.$e->getMessage());
        }

        $manager->setTenant($tenant);
        (new PressingLavageRelancesModule)->install($tenant);

        $module = Module::where('key', 'pressing_lavage_relances')->first();
        if ($module) {
            $tenant->modules()->syncWithoutDetaching([$module->id => ['enabled' => true]]);
        }

        $this->info('Relances dépôt lavage installé pour '.$tenant->code.'.');

        return self::SUCCESS;
    }
}
