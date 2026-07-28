<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate {--tenant= : Run only for this tenant code}';
    protected $description = 'Run migrations for all (or one) tenant(s). Creates missing tables like clients.';

    public function handle(): int
    {
        $code = $this->option('tenant');
        $tenants = $code
            ? Tenant::where('code', $code)->where('is_active', true)->get()
            : Tenant::where('is_active', true)->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenant(s) found.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info('Migrating tenant: ' . $tenant->name . ' (' . $tenant->code . ')');
            $exitCode = Artisan::call('tenant:migrate', ['tenantCode' => $tenant->code]);
            if ($exitCode !== 0) {
                $this->error(Artisan::output());
                return self::FAILURE;
            }
            $this->line(Artisan::output());
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
