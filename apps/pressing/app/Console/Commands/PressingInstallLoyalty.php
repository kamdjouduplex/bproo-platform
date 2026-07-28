<?php

namespace App\Console\Commands;

use App\Jobs\InstallModuleJob;
use App\Models\Module;
use App\Services\ModuleRegistry;
use App\Services\ModulesCatalogSync;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Pressing\Modules\PressingLoyaltyModule;

class PressingInstallLoyalty extends Command
{
    protected $signature = 'pressing:install-loyalty {tenantCode=pressing}';

    protected $description = 'Enable and install the pressing loyalty module for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        ModulesCatalogSync::syncFromConfig(preserveDefaults: true);

        try {
            (new InstallModuleJob($tenant->id, 'pressing_loyalty'))->handle(app(ModuleRegistry::class));
        } catch (\Throwable $e) {
            $this->warn('  → pressing_loyalty: '.$e->getMessage());
        }

        $manager->setTenant($tenant);
        (new PressingLoyaltyModule)->install($tenant);

        $module = Module::where('key', 'pressing_loyalty')->first();
        if ($module) {
            $tenant->modules()->syncWithoutDetaching([$module->id => ['enabled' => true]]);
        }

        $this->info('Fidélité installée pour '.$tenant->code.'.');

        return self::SUCCESS;
    }
}
