<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Debts\DebtsModule;

class DebtsInstallTenant extends Command
{
    protected $signature = 'debts:install-tenant {tenantCode}';
    protected $description = 'Run debts module install (permissions) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new DebtsModule())->install($tenant);
        $this->info('Debts module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
