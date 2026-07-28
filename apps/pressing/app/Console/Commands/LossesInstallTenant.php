<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use InovCom\Losses\LossesModule;

class LossesInstallTenant extends Command
{
    protected $signature = 'losses:install-tenant {tenantCode}';
    protected $description = 'Run losses module install (permissions, default reasons) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (!$tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new LossesModule())->install($tenant);
        $this->info('Losses module installed for ' . $tenant->code . '.');

        return self::SUCCESS;
    }
}
