<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class RestoreDemoTenant extends Command
{
    protected $signature = 'tenant:restore-demo {--admin-name=Admin} {--admin-email=admin@demo.com} {--admin-password=password}';
    protected $description = 'Restore the demo tenant database and provision it with basic data';

    public function handle(): int
    {
        $this->info('Restoring demo tenant...');

        // Ensure tenant record exists
        $tenant = Tenant::where('code', 'demo')->first();

        if (!$tenant) {
            $this->info('Creating demo tenant record...');
            $tenant = Tenant::create([
                'name' => 'Boutique Demo',
                'code' => 'demo',
                'db_name' => 'inovcom_demo',
                'db_host' => null,
                'db_port' => null,
                'db_username' => null,
                'db_password' => null,
                'is_active' => true,
                'metadata' => ['currency' => 'XOF'],
                'provisioning_status' => 'pending',
            ]);
            $this->info('✓ Demo tenant record created');
        } else {
            $this->info('✓ Demo tenant record found');
            // Reset provisioning status
            $tenant->update([
                'provisioning_status' => 'pending',
                'provisioning_error' => null,
            ]);
        }

        // Check if database exists
        try {
            $connection = \Illuminate\Support\Facades\DB::connection(config('database.default', 'pgsql'));
            $exists = $connection->selectOne(
                'SELECT 1 FROM pg_database WHERE datname = ?',
                [$tenant->db_name]
            );

            if ($exists) {
                $this->warn("Database '{$tenant->db_name}' already exists.");
                if (!$this->confirm('Do you want to drop and recreate it?', false)) {
                    $this->info('Skipping database recreation. You can manually drop it and run this command again.');
                    return Command::SUCCESS;
                }

                // Drop existing database
                $this->info("Dropping existing database '{$tenant->db_name}'...");
                $connection->statement("DROP DATABASE IF EXISTS \"{$tenant->db_name}\"");
                $this->info('✓ Database dropped');
            }
        } catch (\Throwable $e) {
            $this->warn("Could not check database existence: " . $e->getMessage());
        }

        // Dispatch provisioning job
        $this->info('Dispatching provisioning job...');
        ProvisionTenantJob::dispatch(
            $tenant,
            $this->option('admin-name'),
            $this->option('admin-email'),
            $this->option('admin-password')
        );

        $this->info('✓ Provisioning job dispatched');
        $this->newLine();
        $this->info('The tenant will be provisioned in the background.');
        $this->info('Make sure your queue worker is running: php artisan queue:work');
        $this->newLine();
        $this->info('To check provisioning status:');
        $this->line('  php artisan tenant:check-db-name demo');
        $this->newLine();
        $this->info('Or use sync queue (immediate):');
        $this->line('  Set QUEUE_CONNECTION=sync in .env, then run this command again');

        return Command::SUCCESS;
    }
}
