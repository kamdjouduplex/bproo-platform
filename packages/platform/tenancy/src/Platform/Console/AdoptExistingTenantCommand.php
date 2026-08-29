<?php

namespace Bproo\Platform\Tenancy\Console;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register an already-populated tenant database in the shared landlord
 * without provisioning (no CREATE DATABASE, no seed, no admin overwrite).
 */
class AdoptExistingTenantCommand extends Command
{
    protected $signature = 'tenants:adopt-database
                            {--code= : Unique tenant code used in login URLs}
                            {--name= : Display name}
                            {--db-name= : Existing PostgreSQL database name}
                            {--type=erp : Product type (erp, pharma, pressing, school, bat)}
                            {--copy-modules-from=demo : Tenant code whose enabled modules are copied}
                            {--months=12 : Active subscription length in months}
                            {--dry-run : Print actions without writing}
                            {--force : Skip confirmation}';

    protected $description = 'Attach an existing tenant database to the current landlord (no provision)';

    public function handle(): int
    {
        $code = trim((string) $this->option('code'));
        $name = trim((string) $this->option('name'));
        $dbName = trim((string) $this->option('db-name'));
        $type = Tenant::normalizeType((string) $this->option('type'));
        $copyFrom = trim((string) $this->option('copy-modules-from'));
        $months = max(1, (int) $this->option('months'));
        $dryRun = (bool) $this->option('dry-run');

        $landlord = (string) config('database.connections.'.config('database.default').'.database');

        if ($landlord === '' || $landlord === 'erp_system_clone') {
            $this->error("Landlord is [{$landlord}]. Run this on Control Center or myerp pointed at bproo_landlord, not the clone landlord.");

            return self::FAILURE;
        }

        foreach (['code' => $code, 'name' => $name, 'db-name' => $dbName] as $opt => $val) {
            if ($val === '') {
                $this->error("Missing required option --{$opt}");

                return self::FAILURE;
            }
        }

        if (Tenant::where('code', $code)->exists()) {
            $this->error("Tenant code [{$code}] already exists in {$landlord}.");

            return self::FAILURE;
        }

        if (Tenant::where('db_name', $dbName)->exists()) {
            $this->error("Database [{$dbName}] is already attached to another tenant.");

            return self::FAILURE;
        }

        $dbExists = DB::selectOne('SELECT 1 AS ok FROM pg_database WHERE datname = ?', [$dbName]);
        if (! $dbExists) {
            $this->error("PostgreSQL database [{$dbName}] does not exist on this cluster.");

            return self::FAILURE;
        }

        $source = $copyFrom !== '' ? Tenant::where('code', $copyFrom)->first() : null;
        if ($copyFrom !== '' && ! $source) {
            $this->warn("Source tenant [{$copyFrom}] not found — modules will not be copied.");
        }

        $plan = $this->resolvePlan($source);

        $this->table(['Field', 'Value'], [
            ['landlord', $landlord],
            ['code', $code],
            ['name', $name],
            ['db_name', $dbName],
            ['type', $type],
            ['copy_modules_from', $source ? $source->code.' (#'.$source->id.')' : '(none)'],
            ['plan', $plan ? $plan->name.' (#'.$plan->id.')' : '(none)'],
            ['subscription_months', (string) $months],
        ]);

        if ($dryRun) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Create this tenant record without provisioning?', true)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $tenant = DB::transaction(function () use ($code, $name, $dbName, $type, $source, $plan, $months) {
            $payload = [
                'name' => $name,
                'code' => $code,
                'db_name' => $dbName,
                'db_host' => null,
                'db_port' => null,
                'db_username' => null,
                'is_active' => true,
                'type' => $type,
                'provisioning_status' => 'completed',
                'provisioning_error' => null,
                'provisioned_at' => now(),
                'metadata' => [
                    'currency' => 'XOF',
                    'adopted_from' => 'existing-database',
                ],
            ];

            if (Schema::hasColumn('tenants', 'multi_store_enabled')) {
                $payload['multi_store_enabled'] = false;
                $payload['multi_store_setup_status'] = 'disabled';
            }

            $tenant = Tenant::create($payload);

            $this->copyModules($tenant, $source);
            $this->copySettings($tenant, $source);
            $this->createSubscription($tenant, $plan, $months);

            return $tenant;
        });

        $this->info("Adopted tenant #{$tenant->id} ({$tenant->code}) → {$tenant->db_name}");
        $login = rtrim((string) config("tenant_types.types.{$type}.base_url"), '/');
        $path = (string) config("tenant_types.types.{$type}.login_path", '/app/login');
        if ($login !== '') {
            $this->line('Login: '.$login.$path.'?tenant='.urlencode($tenant->code));
        }

        return self::SUCCESS;
    }

    private function resolvePlan(?Tenant $source): ?Plan
    {
        $planId = $source?->currentSubscription()?->plan_id;
        if ($planId) {
            $plan = Plan::find($planId);
            if ($plan) {
                return $plan;
            }
        }

        return Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
    }

    private function copyModules(Tenant $tenant, ?Tenant $source): void
    {
        if (! $source) {
            return;
        }

        $rows = DB::table('tenant_modules')->where('tenant_id', $source->id)->get();
        $now = now();

        foreach ($rows as $row) {
            $insert = [
                'tenant_id' => $tenant->id,
                'module_id' => $row->module_id,
                'enabled' => (bool) $row->enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('tenant_modules', 'installed_version')) {
                $insert['installed_version'] = $row->installed_version ?? null;
            }
            if (Schema::hasColumn('tenant_modules', 'installed_at')) {
                $insert['installed_at'] = $row->installed_at ?? $now;
            }

            DB::table('tenant_modules')->insert($insert);
        }

        $this->info('Copied '.count($rows).' module row(s) from '.$source->code.'.');
    }

    private function copySettings(Tenant $tenant, ?Tenant $source): void
    {
        if ($source) {
            foreach ($source->settings as $setting) {
                $tenant->setSetting($setting->key, $setting->value);
            }
        }

        if (! $tenant->getSetting('currency')) {
            $tenant->setSetting('currency', 'XOF');
        }
        if (! $tenant->getSetting('locale')) {
            $tenant->setSetting('locale', 'fr');
        }
        if (! $tenant->getSetting('timezone')) {
            $tenant->setSetting('timezone', 'Africa/Douala');
        }
    }

    private function createSubscription(Tenant $tenant, ?Plan $plan, int $months): void
    {
        if (! $plan) {
            $this->warn('No plan found — create a subscription from Control Center.');

            return;
        }

        $payload = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonths($months)->toDateString(),
            'activated_at' => now(),
        ];

        if (Schema::hasColumn('subscriptions', 'billing_mode')) {
            $payload['billing_mode'] = $plan->billing_mode ?: Plan::MODE_FLAT;
        }

        Subscription::create($payload);
        $this->info("Active subscription on plan [{$plan->name}] for {$months} month(s).");
    }
}
