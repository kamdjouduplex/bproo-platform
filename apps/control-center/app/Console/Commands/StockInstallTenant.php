<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Stock\StockModule;

class StockInstallTenant extends Command
{
    protected $signature = 'stock:install-tenant {tenantCode}';
    protected $description = 'Run stock module install (permissions) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new StockModule())->install($tenant);
        $this->info('Stock module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
