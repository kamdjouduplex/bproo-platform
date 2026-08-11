<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Providers\ProvidersModule;

class ProvidersInstallTenant extends Command
{
    protected $signature = 'providers:install-tenant {tenantCode}';
    protected $description = 'Run providers module install (permissions, default payment terms) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new ProvidersModule())->install($tenant);
        $this->info('Providers module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
