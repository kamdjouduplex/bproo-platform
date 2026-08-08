<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Sales\SalesModule;

class SalesInstallTenant extends Command
{
    protected $signature = 'sales:install-tenant {tenantCode}';
    protected $description = 'Run sales module install (permissions) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new SalesModule())->install($tenant);
        $this->info('Sales module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
