<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantSeed extends Command
{
    protected $signature = 'tenant:seed {tenantCode} {--class=}';
    protected $description = 'Seed a tenant database after resolving tenant connection.';

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

        $params = [
            '--database' => 'tenant',
            '--force' => true,
        ];

        if ($this->option('class')) {
            $params['--class'] = $this->option('class');
        }

        $exitCode = Artisan::call('db:seed', $params);
        $this->info(Artisan::output());

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
