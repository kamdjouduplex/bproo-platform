<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\ModulesCatalogSync;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use InovCom\Users\UsersModule;

/**
 * Bootstrap local landlord + tenant SQLite for DESKTOP_RUNTIME installs.
 * SaaS Postgres provisioning is intentionally not used here.
 */
class DesktopBootstrapCommand extends Command
{
    protected $signature = 'desktop:bootstrap
        {--code=pharma : Tenant code}
        {--name=Officine locale : Tenant display name}
        {--admin-email=admin@officine.local : First admin email}
        {--admin-password=password : First admin password}
        {--admin-name=Administrateur : First admin name}
        {--force : Re-run even if already bootstrapped}';

    protected $description = 'Prepare local SQLite tenancy for Pharma Desktop (landlord + tenant DB, admin user)';

    public function handle(TenantManager $manager): int
    {
        if (! filter_var(env('DESKTOP_RUNTIME', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->error('DESKTOP_RUNTIME=1 requis.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'sqlite') {
            $this->error('desktop:bootstrap attend DB_CONNECTION=sqlite.');

            return self::FAILURE;
        }

        $code = strtolower(trim((string) $this->option('code')));
        $tenantDbPath = database_path('tenant.sqlite');
        $landlordPath = (string) config('database.connections.sqlite.database');

        $this->ensureSqliteFile($landlordPath);
        $this->ensureSqliteFile($tenantDbPath);

        $this->info('==> Landlord migrate');
        if ($this->callArtisan('migrate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('tenants')) {
            $this->error('Table tenants absente après migrate. Vérifiez DB_DATABASE / droits d’écriture.');

            return self::FAILURE;
        }

        $this->info('==> Catalogue modules + plans');
        ModulesCatalogSync::syncFromConfig(preserveDefaults: false);
        if ($this->callArtisan('db:seed', ['--class' => \Database\Seeders\PlansSeeder::class, '--force' => true]) !== 0) {
            return self::FAILURE;
        }

        $existing = Tenant::query()->where('code', $code)->first();
        if ($existing && ! $this->option('force') && $existing->provisioning_status === 'completed') {
            $this->warn("Tenant {$code} déjà provisionné. Utilisez --force pour réinstaller.");
            $this->printLoginHints($code);

            return self::SUCCESS;
        }

        $this->info("==> Tenant landlord ({$code})");
        $tenant = Tenant::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => (string) $this->option('name'),
                'db_name' => $tenantDbPath,
                'db_host' => null,
                'db_port' => null,
                'db_username' => null,
                'db_password' => null,
                'is_active' => true,
                'type' => 'pharma',
                'provisioning_status' => 'provisioning',
                'provisioning_error' => null,
            ]
        );

        $tenant->setSetting('shop_name', (string) $this->option('name'));
        $tenant->setSetting('currency', config('inovcom.default_currency', 'XOF'));
        $tenant->setSetting('locale', config('inovcom.default_locale', 'fr'));
        $tenant->setSetting('timezone', config('inovcom.default_timezone', 'Africa/Douala'));

        $this->ensureLocalSubscription($tenant);
        $this->enableDefaultModules($tenant);

        config(['inovcom.tenant.database_driver' => 'sqlite']);

        $this->info('==> Tenant migrate');
        $manager->setTenant($tenant->fresh());
        if (! $this->migrateTenantSqliteSafe()) {
            $tenant->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => 'tenant migrate failed',
            ]);

            return self::FAILURE;
        }

        $this->info('==> Users module + admin');
        $manager->setTenant($tenant->fresh());
        (new UsersModule())->install($tenant->fresh());

        // Install other enabled modules (best-effort — login must still work if one fails)
        try {
            $registry = app(\App\Services\ModuleRegistry::class);
            foreach ($tenant->fresh()->modules()->wherePivot('enabled', true)->get() as $module) {
                if ($module->key === 'users') {
                    continue;
                }
                try {
                    $registry->install($module->key, $tenant->fresh());
                } catch (\Throwable $e) {
                    $this->warn("Module {$module->key}: ".$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->warn('ModuleRegistry: '.$e->getMessage());
        }

        $this->ensureAdminUser(
            $tenant->fresh(),
            (string) $this->option('admin-name'),
            (string) $this->option('admin-email'),
            (string) $this->option('admin-password')
        );

        $tenant->update([
            'provisioning_status' => 'completed',
            'provisioning_error' => null,
            'provisioned_at' => now(),
        ]);

        $this->info('Bootstrap desktop OK.');
        $this->printLoginHints($code);

        return self::SUCCESS;
    }

    private function ensureSqliteFile(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! is_file($path)) {
            touch($path);
        }
    }

    private function ensureLocalSubscription(Tenant $tenant): void
    {
        $plan = Plan::query()->where('slug', 'demo')->first()
            ?? Plan::query()->orderBy('id')->first();

        if (! $plan) {
            throw new \RuntimeException('Aucun plan trouvé après PlansSeeder.');
        }

        // Offline installs: short local trial window (not the CC SaaS period).
        $days = max(1, (int) env('DESKTOP_LOCAL_SUBSCRIPTION_DAYS', 30));
        $periodEnd = now()->addDays($days)->toDateString();

        $sub = $tenant->subscriptions()->first();
        if ($sub) {
            $sub->fill([
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_start' => now()->toDateString(),
                'current_period_end' => $periodEnd,
                'grace_ends_at' => $periodEnd,
                'activated_at' => $sub->activated_at ?: now(),
                'suspended_at' => null,
                'cancelled_at' => null,
                'suspension_reason' => null,
            ])->save();

            return;
        }

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => now()->toDateString(),
            'current_period_end' => $periodEnd,
            'grace_ends_at' => $periodEnd,
            'activated_at' => now(),
        ]);
    }

    private function enableDefaultModules(Tenant $tenant): void
    {
        $modulesConfig = config('modules', []);
        $modules = Module::query()->orderBy('label')->get();
        $sync = [];

        foreach ($modules as $module) {
            $cfg = $modulesConfig[$module->key] ?? null;
            $enabled = is_array($cfg)
                ? (bool) ($cfg['enabled_by_default'] ?? false)
                : (bool) $module->enabled_by_default;

            $types = is_array($cfg) ? ($cfg['tenant_types'] ?? []) : [];
            if ($enabled && is_array($types) && $types !== [] && ! in_array('pharma', $types, true)) {
                $enabled = false;
            }

            $sync[$module->id] = [
                'enabled' => $enabled,
            ];
        }

        if ($sync !== []) {
            $tenant->modules()->syncWithoutDetaching($sync);
        }
    }

    /**
     * Run tenant migrations file-by-file. Skip SQLite-incompatible alters (pgsql CHECK/FK DDL)
     * after recording them, so desktop bootstrap can still reach a login-ready schema.
     */
    private function migrateTenantSqliteSafe(): bool
    {
        $conn = \Illuminate\Support\Facades\DB::connection('tenant');
        if (! Schema::connection('tenant')->hasTable('migrations')) {
            if ($this->callArtisan('migrate:install', ['--database' => 'tenant']) !== 0) {
                return false;
            }
        }

        $ran = 0;
        $skipped = 0;
        foreach (['tenant', 'tenant_modules'] as $subdir) {
            $dir = database_path('migrations'.DIRECTORY_SEPARATOR.$subdir);
            if (! is_dir($dir)) {
                continue;
            }
            $files = collect(\Illuminate\Support\Facades\File::files($dir))
                ->sortBy(fn ($f) => $f->getFilename())
                ->values();

            foreach ($files as $file) {
                $name = $file->getFilenameWithoutExtension();
                if ($conn->table('migrations')->where('migration', $name)->exists()) {
                    continue;
                }

                $rel = 'database/migrations/'.$subdir.'/'.$file->getFilename();
                try {
                    $code = Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => $rel,
                        '--force' => true,
                    ]);
                    $out = trim(Artisan::output());
                    if ($out !== '') {
                        $this->line($out);
                    }
                    if ($code !== 0) {
                        throw new \RuntimeException($out !== '' ? $out : "migrate failed: {$name}");
                    }
                    $ran++;
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    $skipable = str_contains($msg, 'syntax error')
                        || str_contains($msg, 'DROP CONSTRAINT')
                        || str_contains($msg, 'DROP FOREIGN KEY')
                        || str_contains($msg, 'after drop column')
                        || str_contains($msg, 'no such column')
                        || str_contains($msg, 'Cannot add a NOT NULL column');

                    if (! $skipable) {
                        $this->error("Tenant migrate bloqué sur {$name}: {$msg}");

                        return false;
                    }

                    $this->warn("SQLite skip {$name}");
                    $batch = (int) ($conn->table('migrations')->max('batch') ?? 0);
                    $conn->table('migrations')->insert([
                        'migration' => $name,
                        'batch' => $batch + 1,
                    ]);
                    $skipped++;
                }
            }
        }

        $this->info("Tenant migrate: {$ran} ok, {$skipped} skipped (sqlite).");

        return Schema::connection('tenant')->hasTable('users');
    }

    private function ensureAdminUser(Tenant $tenant, string $name, string $email, string $password): void
    {
        app(TenantManager::class)->setTenant($tenant);

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

    private function callArtisan(string $command, array $params = []): int
    {
        $code = Artisan::call($command, $params);
        $out = trim(Artisan::output());
        if ($out !== '') {
            $this->line($out);
        }

        return $code;
    }

    private function printLoginHints(string $code): void
    {
        $email = (string) $this->option('admin-email');
        $password = (string) $this->option('admin-password');
        $this->line("Login: /app/login?tenant={$code}");
        $this->line("Compte: {$email} / {$password}");
    }
}
