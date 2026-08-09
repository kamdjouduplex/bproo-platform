<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CompanyIntelligenceService
{
    /**
     * Refresh cached metrics for one company (tenant DB fan-out).
     *
     * users_count = billable seats (active users when is_active exists).
     *
     * @return array{
     *   users_count: int|null,
     *   users_total: int|null,
     *   modules_enabled_count: int,
     *   db_ok: bool,
     *   error: ?string,
     *   last_tenant_activity_at: mixed,
     *   users_limit_exceeded: bool,
     *   users_limit_newly_exceeded: bool
     * }
     */
    public function refresh(Tenant $tenant, bool $persist = true): array
    {
        $tenant->refresh();

        $modulesEnabled = (int) $tenant->modules()->wherePivot('enabled', true)->count();
        $usersCount = null;
        $usersTotal = null;
        $dbOk = false;
        $error = null;
        $lastActivity = null;
        $wasExceeded = (bool) $tenant->users_limit_exceeded_at;

        try {
            config(['database.connections.tenant' => $tenant->databaseConfig()]);
            DB::purge('tenant');
            DB::reconnect('tenant');
            DB::connection('tenant')->select('select 1');
            $dbOk = true;

            if (Schema::connection('tenant')->hasTable('users')) {
                $usersTotal = (int) DB::connection('tenant')->table('users')->count();
                if (Schema::connection('tenant')->hasColumn('users', 'is_active')) {
                    // Billable seats = active accounts only
                    $usersCount = (int) DB::connection('tenant')->table('users')
                        ->where('is_active', true)
                        ->count();
                } else {
                    $usersCount = $usersTotal;
                }
            }

            if (Schema::connection('tenant')->hasTable('sessions')) {
                $lastActivity = DB::connection('tenant')->table('sessions')->max('last_activity');
                if (is_numeric($lastActivity)) {
                    $lastActivity = \Carbon\Carbon::createFromTimestamp((int) $lastActivity);
                } else {
                    $lastActivity = null;
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Company intelligence refresh failed', [
                'tenant' => $tenant->code,
                'error' => $error,
            ]);
        }

        $exceeded = $tenant->isUsersLimitExceeded($usersCount);
        $newlyExceeded = $exceeded && ! $wasExceeded;

        if ($persist) {
            $tenant->forceFill([
                'users_count' => $usersCount,
                'modules_enabled_count' => $modulesEnabled,
                'last_tenant_activity_at' => $lastActivity,
                'metrics_cached_at' => now(),
                'users_limit_exceeded_at' => $exceeded
                    ? ($tenant->users_limit_exceeded_at ?? now())
                    : null,
            ])->save();

            if ($newlyExceeded) {
                Log::warning('Tenant exceeded max_users quota', [
                    'tenant' => $tenant->code,
                    'users_count' => $usersCount,
                    'users_total' => $usersTotal,
                    'max_users' => $tenant->max_users,
                ]);
            }
        }

        return [
            'users_count' => $usersCount,
            'users_total' => $usersTotal,
            'modules_enabled_count' => $modulesEnabled,
            'db_ok' => $dbOk,
            'error' => $error,
            'last_tenant_activity_at' => $lastActivity,
            'users_limit_exceeded' => $exceeded,
            'users_limit_newly_exceeded' => $newlyExceeded,
        ];
    }

    /**
     * @return array{refreshed: int, newly_exceeded: list<string>}
     */
    public function refreshAll(?int $limit = null): array
    {
        $query = Tenant::query()
            ->where('provisioning_status', 'completed')
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $n = 0;
        $newlyExceeded = [];
        foreach ($query->cursor() as $tenant) {
            $result = $this->refresh($tenant, true);
            $n++;
            if (! empty($result['users_limit_newly_exceeded'])) {
                $newlyExceeded[] = $tenant->code;
            }
        }

        return [
            'refreshed' => $n,
            'newly_exceeded' => $newlyExceeded,
        ];
    }

    /**
     * Tenants over their seat quota (flag or live cached count).
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    public function tenantsExceedingUsersLimit()
    {
        return Tenant::query()
            ->where(function ($q) {
                $q->whereNotNull('users_limit_exceeded_at')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('max_users')
                            ->where('max_users', '>', 0)
                            ->whereNotNull('users_count')
                            ->whereColumn('users_count', '>', 'max_users');
                    });
            })
            ->orderByDesc('users_limit_exceeded_at')
            ->orderByDesc('users_count')
            ->get();
    }
}
