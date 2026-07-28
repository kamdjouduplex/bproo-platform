<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Inventory\InventoryModule;

class InventoryInstallTenant extends Command
{
    protected $signature = 'inventory:install-tenant {tenantCode}';
    protected $description = 'Run inventory module install (permissions, default reasons) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new InventoryModule())->install($tenant);
        $this->info('Inventory module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
