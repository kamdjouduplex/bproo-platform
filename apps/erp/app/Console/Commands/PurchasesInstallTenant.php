<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Purchases\PurchasesModule;

class PurchasesInstallTenant extends Command
{
    protected $signature = 'purchases:install-tenant {tenantCode}';
    protected $description = 'Run purchases module install (permissions) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new PurchasesModule())->install($tenant);
        $this->info('Purchases module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
