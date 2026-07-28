<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Pressing\Support\PressingRolePermissions;

class PressingSyncRoles extends Command
{
    protected $signature = 'pressing:sync-roles {tenantCode=pressing}';

    protected $description = 'Align pressing operational roles (Réception, Production, Repassage, Livreur, …) with least-privilege permissions';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->resolveByCode($this->argument('tenantCode'));
        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $manager->setTenant($tenant);
        app()->instance('tenant', $tenant);

        $synced = PressingRolePermissions::syncAll();

        if ($synced === []) {
            $this->warn('No matching roles found to sync.');

            return self::SUCCESS;
        }

        $this->info('Permissions synced:');
        foreach ($synced as $role => $count) {
            $this->line("  • {$role}: {$count} permission(s)");
        }

        return self::SUCCESS;
    }
}
