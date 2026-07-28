<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Expenses\ExpensesModule;

class ExpensesInstallTenant extends Command
{
    protected $signature = 'expenses:install-tenant {tenantCode}';
    protected $description = 'Run expenses module install (permissions, default categories) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new ExpensesModule())->install($tenant);
        $this->info('Expenses module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
