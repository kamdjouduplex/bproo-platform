<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Pressing\Modules\PressingOrdersModule;

class PressingInstallTenant extends Command
{
    protected $signature = 'pressing:install-tenant {tenantCode}';

    protected $description = 'Sync pressing orders permissions (incl. credit) for a tenant.';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        (new PressingOrdersModule)->install($tenant);
        $this->info('Pressing orders permissions synced for '.$tenant->code.'.');

        return self::SUCCESS;
    }
}
