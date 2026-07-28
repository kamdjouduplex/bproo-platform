<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Caisse\CaisseModule;

class CaisseInstallTenant extends Command
{
    protected $signature = 'caisse:install-tenant {tenantCode}';
    protected $description = 'Run caisse module install (permissions and ledger init) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new CaisseModule())->install($tenant);
        $this->info('Caisse module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
