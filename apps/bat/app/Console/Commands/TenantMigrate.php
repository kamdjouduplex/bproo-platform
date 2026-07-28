<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate {tenantCode} {--fresh} {--seed}';
    protected $description = 'Run tenant migrations for a specific tenant code.';

    public function handle(): int
    {
        $tenantCode = $this->argument('tenantCode');
        $manager = app(TenantManager::class);
        $tenant = $manager->resolveByCode($tenantCode);

        if (!$tenant) {
            $this->error('Tenant not found: ' . $tenantCode);
            return self::FAILURE;
        }

        $manager->setTenant($tenant);

        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';
        $params = [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ];

        $exitCode = Artisan::call($command, $params);

        if ($this->option('seed')) {
            Artisan::call('db:seed', ['--database' => 'tenant', '--force' => true]);
        }

        $this->info(Artisan::output());

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
