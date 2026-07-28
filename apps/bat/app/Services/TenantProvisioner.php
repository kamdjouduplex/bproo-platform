<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantProvisioner
{
    private ?string $currentTenantCode = null;

    public function provision(Tenant $tenant, string $adminName, string $adminEmail, string $adminPassword): void
    {
        $this->currentTenantCode = $tenant->code;
        $this->logStep($tenant->code, 'start');

        try {
            // Step 1: Ensure database exists
            $this->ensureDatabaseExists($tenant);
            $this->logStep($tenant->code, 'database_ready');

            // Step 2: Run migrations
            $this->migrateTenantDatabase($tenant);
            $this->logStep($tenant->code, 'migrations_done');

            // Step 3: Seed default modules
            $this->seedDefaultModules($tenant);
            $this->logStep($tenant->code, 'modules_seeded');

            // Step 4: Seed default settings
            $this->seedDefaultSettings($tenant);
            $this->logStep($tenant->code, 'settings_seeded');

            // Step 5: Create admin user
            $this->createAdminUser($tenant, $adminName, $adminEmail, $adminPassword);
            $this->logStep($tenant->code, 'admin_created');

        } catch (\Throwable $e) {
            $this->logStep($tenant->code, 'error: ' . $e->getMessage());
            
            // Log full exception details
            Log::error("Tenant provisioning failed for {$tenant->code}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }

        $this->logStep($tenant->code, 'done');
    }

    private function ensureDatabaseExists(Tenant $tenant): void
    {
        // Refresh tenant to ensure we have the latest db_name
        $tenant->refresh();
        $dbName = $tenant->db_name;
        
        // Log the db_name being used for debugging
        $this->logStep($tenant->code, "using_db_name: {$dbName}");
        
        if (empty($dbName)) {
            throw new \RuntimeException("Database name is empty for tenant {$tenant->code}");
        }
        
        $connection = DB::connection(config('database.default', 'pgsql'));
        $this->applyStatementTimeout($connection);

        $this->logStep($tenant->code, 'checking_database');
        $exists = $connection->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName]);
        if ($exists) {
            $this->logStep($tenant->code, 'database_exists');
            return;
        }

        $this->logStep($tenant->code, "creating_database: {$dbName}");
        try {
            $connection->statement('CREATE DATABASE "' . str_replace('"', '""', $dbName) . '"');
            $this->logStep($tenant->code, "database_created: {$dbName}");
        } catch (\Throwable $e) {
            $this->logStep($tenant->code, 'database_creation_failed: ' . $e->getMessage());
            throw new \RuntimeException("Failed to create database {$dbName}: " . $e->getMessage(), 0, $e);
        }
    }

    private function migrateTenantDatabase(Tenant $tenant): void
    {
        $this->logStep($tenant->code, 'publish_core_migrations');
        foreach (config('modules.core_migration_tags', []) as $tag) {
            Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);
        }

        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $this->applyStatementTimeout(DB::connection('tenant'));

        $this->logStep($tenant->code, 'migrate_install');
        if (!Schema::connection('tenant')->hasTable('migrations')) {
            $this->runArtisanOrFail('migrate:install', ['--database' => 'tenant']);
        }

        $this->logStep($tenant->code, 'migrate_run');
        $this->runArtisanOrFail('migrate', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);
    }

    private function seedDefaultModules(Tenant $tenant): void
    {
        $registry = app(ModuleRegistry::class);
        $modules  = Module::orderBy('label')->get();

        $this->logStep($tenant->code, 'seed_modules');
        foreach ($modules as $module) {
            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['enabled' => $module->enabled_by_default],
            ]);

            if ($module->enabled_by_default) {
                $registry->install($module->key, $tenant);
            }
        }
    }

    private function seedDefaultSettings(Tenant $tenant): void
    {
        $this->logStep($tenant->code, 'seed_settings');

        $defaults = [
            // Identité
            'shop_name'             => $tenant->name,
            'login_welcome_message' => 'Bienvenue dans votre espace de gestion',
            'company_tagline'       => '',
            'company_website'       => '',
            // Coordonnées
            'company_email'         => '',
            'company_phone'         => '',
            'company_phone2'        => '',
            'company_address'       => '',
            'company_city'          => '',
            'company_country'       => 'Cameroun',
            'company_postal_code'   => '',
            'company_registration'  => '',
            'company_tax_id'        => '',
            // Finance
            'currency'              => config('inovcom.default_currency', 'XOF'),
            'tax_rate'              => '19.25',
            'invoice_prefix'        => 'FAC',
            'proforma_prefix'       => 'PRO',
            'credit_note_prefix'    => 'AV',
            'quote_prefix'          => 'DEV',
            'payment_terms'         => '30',
            'bank_name'             => '',
            'bank_account'          => '',
            'bank_iban'             => '',
            'invoice_footer'        => '',
            // Régionalisation
            'locale'                => config('inovcom.default_locale', 'fr'),
            'timezone'              => 'Africa/Douala',
            'date_format'           => 'd/m/Y',
        ];

        foreach ($defaults as $key => $value) {
            $tenant->setSetting($key, $value);
        }
    }

    private function createAdminUser(Tenant $tenant, string $name, string $email, string $password): void
    {
        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $this->applyStatementTimeout(DB::connection('tenant'));

        // Seed permissions & roles first so the admin user can be assigned a role
        $this->logStep($tenant->code, 'seed_permissions');
        $this->runArtisanOrFail('tenant:seed', [
            'tenantCode' => $tenant->code,
            '--class'    => 'TenantPermissionSeeder',
        ]);
        $this->logStep($tenant->code, 'permissions_seeded');

        // Re-establish tenant connection after the seeder (which may have re-configured it)
        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $this->applyStatementTimeout(DB::connection('tenant'));

        $this->logStep($tenant->code, 'seed_admin');
        $user = User::on('tenant')->firstOrCreate(
            ['email' => $email],
            [
                'name'           => $name,
                'password'       => Hash::make($password),
                'is_active'      => true,
                'remember_token' => Str::random(10),
            ]
        );

        // Role name is 'Admin' (capital A) as created by TenantPermissionSeeder
        $adminRole = Role::on('tenant')->where('name', 'Admin')->first();
        if ($adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }

    private function runArtisanOrFail(string $command, array $parameters = []): void
    {
        try {
            $exitCode = Artisan::call($command, $parameters);
            if ($exitCode !== 0) {
                $output = trim(Artisan::output());
                throw new \RuntimeException($output !== '' ? $output : "Command failed: {$command}");
            }
        } catch (\Throwable $e) {
            $this->logStep($this->getCurrentTenantCode(), "command_failed: {$command} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get current tenant code for logging (helper method)
     */
    private function getCurrentTenantCode(): string
    {
        return $this->currentTenantCode ?? 'unknown';
    }

    private function applyStatementTimeout($connection): void
    {
        try {
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement('SET statement_timeout TO 30000');
            }
        } catch (\Throwable $e) {
            // Non-critical: continue without timeout.
        }
    }

    private function logStep(string $tenantCode, string $step): void
    {
        $message = '[' . now()->toDateTimeString() . '] tenant=' . $tenantCode . ' step=' . $step;
        Log::info($message);

        $path = storage_path('logs/tenant-provisioning.log');
        @file_put_contents($path, $message . PHP_EOL, FILE_APPEND);
    }
}
