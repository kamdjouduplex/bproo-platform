<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTenantDbName extends Command
{
    protected $signature = 'tenant:check-db-name {code? : Tenant code}';
    protected $description = 'Check tenant database name and verify it exists';

    public function handle(): int
    {
        $code = $this->argument('code');

        if ($code) {
            $tenant = Tenant::where('code', $code)->first();
            if (!$tenant) {
                $this->error("Tenant '{$code}' not found.");
                return Command::FAILURE;
            }
            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::all();
        }

        $this->info('Checking tenant database names...');
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->line("Tenant: {$tenant->name} ({$tenant->code})");
            $this->line("  DB Name in database: {$tenant->db_name}");
            $this->line("  Provisioning Status: {$tenant->provisioning_status}");

            // Check if database exists
            try {
                $connection = DB::connection(config('database.default', 'pgsql'));
                $exists = $connection->selectOne(
                    'SELECT 1 FROM pg_database WHERE datname = ?',
                    [$tenant->db_name]
                );

                if ($exists) {
                    $this->info("  ✓ Database '{$tenant->db_name}' exists");
                } else {
                    $this->warn("  ✗ Database '{$tenant->db_name}' does NOT exist");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Error checking database: " . $e->getMessage());
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }
}
