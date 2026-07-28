<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Tickets\TicketsModule;

class TicketsInstallTenant extends Command
{
    protected $signature = 'tickets:install-tenant {tenantCode}';
    protected $description = 'Run tickets module install (permissions) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new TicketsModule())->install($tenant);
        $this->info('Tickets module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
