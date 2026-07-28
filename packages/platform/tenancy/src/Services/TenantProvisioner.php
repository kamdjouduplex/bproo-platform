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
        
        $connection = $this->databaseAdminConnection();
        $this->applyStatementTimeout($connection);

        $this->logStep($tenant->code, 'checking_database');
        $exists = $connection->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName]);
        if ($exists) {
            $this->logStep($tenant->code, 'database_exists');
            return;
        }

        $owner = $this->appDatabaseOwner();
        $escapedDb = str_replace('"', '""', $dbName);
        $escapedOwner = str_replace('"', '""', $owner);

        $this->logStep($tenant->code, "creating_database: {$dbName} owner={$owner}");
        try {
            $connection->statement("CREATE DATABASE \"{$escapedDb}\" OWNER \"{$escapedOwner}\"");
            $this->logStep($tenant->code, "database_created: {$dbName}");
        } catch (\Throwable $e) {
            $this->logStep($tenant->code, 'database_creation_failed: ' . $e->getMessage());
            throw new \RuntimeException("Failed to create database {$dbName}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Use postgres superuser when DB_PROVISION_PASSWORD is set; otherwise fall back to app user (needs CREATEDB).
     */
    private function databaseAdminConnection()
    {
        $provisionPassword = config('database.connections.pgsql_provision.password');
        if ($provisionPassword !== null && $provisionPassword !== '') {
            return DB::connection('pgsql_provision');
        }

        return DB::connection(config('database.default', 'pgsql'));
    }

    private function appDatabaseOwner(): string
    {
        return (string) config('database.connections.pgsql.username', env('DB_USERNAME', 'erp_app'));
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
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    private function seedDefaultModules(Tenant $tenant): void
    {
        ModulesCatalogSync::syncFromConfig(preserveDefaults: true);

        $registry = app(ModuleRegistry::class);
        $modules = Module::orderBy('label')->get();
        $modulesConfig = config('modules', []);
        $tenantType = $tenant->type ?? config('tenant_types.default', 'retail');
        $existingPivots = $tenant->modules()->get()->keyBy('id');

        $this->logStep($tenant->code, 'seed_modules');
        foreach ($modules as $module) {
            $existingPivot = $existingPivots->get($module->id);

            if ($existingPivot) {
                $enabled = (bool) $existingPivot->pivot->enabled;
            } else {
                $enabled = $this->resolveInitialModuleEnabled($module, $tenant, $tenantType, $modulesConfig);
                $tenant->modules()->syncWithoutDetaching([
                    $module->id => ['enabled' => $enabled],
                ]);
            }

            if ($enabled) {
                try {
                    $registry->install($module->key, $tenant);
                } catch (\Throwable $e) {
                    Log::warning("Module {$module->key} install failed during provisioning: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Decide whether a module should be enabled on first attach for a new tenant.
     * Optional modules already active on another vendeur are inherited (fleet-wide).
     */
    private function resolveInitialModuleEnabled(
        Module $module,
        Tenant $tenant,
        string $tenantType,
        array $modulesConfig
    ): bool {
        $cfg = $modulesConfig[$module->key] ?? [];

        if (!empty($cfg['core'])) {
            return true;
        }

        $enabledByDefault = (bool) $module->enabled_by_default;
        $tenantTypes = $cfg['tenant_types'] ?? [];
        if ($enabledByDefault && !empty($tenantTypes) && !in_array($tenantType, $tenantTypes, true)) {
            $enabledByDefault = false;
        }
        if ($enabledByDefault) {
            return true;
        }

        return DB::table('tenant_modules')
            ->where('module_id', $module->id)
            ->where('tenant_id', '!=', $tenant->id)
            ->where('enabled', true)
            ->exists();
    }

    private function seedDefaultSettings(Tenant $tenant): void
    {
        $this->logStep($tenant->code, 'seed_settings');
        $tenant->setSetting('currency', config('inovcom.default_currency', 'XOF'));
        $tenant->setSetting('locale', config('inovcom.default_locale', 'fr'));
        $tenant->setSetting('timezone', config('inovcom.default_timezone', 'Africa/Douala'));
        $tenant->setSetting('tax_rate', '0');
        $tenant->setSetting('invoice_prefix', 'INV');
    }

    private function createAdminUser(Tenant $tenant, string $name, string $email, string $password): void
    {
        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        $this->applyStatementTimeout(DB::connection('tenant'));

        if (User::on('tenant')->exists()) {
            $this->logStep($tenant->code, 'admin_exists_skip');
            return;
        }

        if ($email === '' || $password === '') {
            throw new \RuntimeException(
                'Identifiants admin requis pour créer le premier utilisateur. Supprimez le vendeur et recréez-le avec un compte admin.'
            );
        }

        $this->logStep($tenant->code, 'seed_admin');
        $user = User::on('tenant')->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );
        $adminRole = Role::on('tenant')->where('name', 'admin')->first();
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
