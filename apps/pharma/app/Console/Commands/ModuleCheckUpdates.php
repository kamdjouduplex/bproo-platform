<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ModuleVersionService;
use Illuminate\Console\Command;

class ModuleCheckUpdates extends Command
{
    protected $signature = 'modules:check-updates {tenant? : Tenant code to check}';
    protected $description = 'Check for available module updates';

    public function handle(ModuleVersionService $versionService): int
    {
        $tenantCode = $this->argument('tenant');

        if ($tenantCode) {
            $tenant = Tenant::where('code', $tenantCode)->first();
            if (!$tenant) {
                $this->error("Tenant '{$tenantCode}' not found.");
                return Command::FAILURE;
            }

            $this->checkTenantUpdates($tenant, $versionService);
        } else {
            $tenants = Tenant::all();
            foreach ($tenants as $tenant) {
                $this->checkTenantUpdates($tenant, $versionService);
            }
        }

        return Command::SUCCESS;
    }

    private function checkTenantUpdates(Tenant $tenant, ModuleVersionService $versionService): void
    {
        $this->info("Checking updates for tenant: {$tenant->name} ({$tenant->code})");

        $updates = $versionService->getAvailableUpdates($tenant);

        if (empty($updates)) {
            $this->line('  ✓ All modules are up to date.');
            return;
        }

        $this->warn("  Found " . count($updates) . " update(s):");

        foreach ($updates as $update) {
            $this->line("  - {$update['module_label']} ({$update['module']})");
            $this->line("    Current: {$update['current_version']} → Available: {$update['available_version']}");
        }
    }
}
