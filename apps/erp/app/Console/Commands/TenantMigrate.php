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
        $paths = [
            'database/migrations/tenant',
            'database/migrations/tenant_modules',
        ];

        $exitCode = 0;
        foreach ($paths as $path) {
            $params = [
                '--database' => 'tenant',
                '--path' => $path,
                '--force' => true,
            ];
            $currentExit = Artisan::call($command, $params);
            $this->info(Artisan::output());
            if ($currentExit !== 0) {
                $exitCode = $currentExit;
                break;
            }
        }

        if ($this->option('seed')) {
            Artisan::call('db:seed', ['--database' => 'tenant', '--force' => true]);
        }

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
