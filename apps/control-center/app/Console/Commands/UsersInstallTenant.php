<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Users\UsersModule;

class UsersInstallTenant extends Command
{
    protected $signature = 'users:install-tenant {tenantCode}';
    protected $description = 'Run users module install (roles, permissions, defaults) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new UsersModule())->install($tenant);
        $this->info('Users module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
