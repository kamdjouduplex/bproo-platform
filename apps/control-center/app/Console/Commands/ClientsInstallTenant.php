<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Clients\ClientsModule;

class ClientsInstallTenant extends Command
{
    protected $signature = 'clients:install-tenant {tenantCode}';
    protected $description = 'Run clients module install (permissions, default segment) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new ClientsModule())->install($tenant);
        $this->info('Clients module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
