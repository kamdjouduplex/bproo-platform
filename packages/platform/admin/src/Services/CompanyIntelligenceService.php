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
     * @return array{users_count: int|null, modules_enabled_count: int, db_ok: bool, error: ?string}
     */
    public function refresh(Tenant $tenant, bool $persist = true): array
    {
        $modulesEnabled = (int) $tenant->modules()->wherePivot('enabled', true)->count();
        $usersCount = null;
        $dbOk = false;
        $error = null;
        $lastActivity = null;

        try {
            config(['database.connections.tenant' => $tenant->databaseConfig()]);
            DB::purge('tenant');
            DB::reconnect('tenant');
            DB::connection('tenant')->select('select 1');
            $dbOk = true;

            if (Schema::connection('tenant')->hasTable('users')) {
                $usersCount = (int) DB::connection('tenant')->table('users')->count();
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

        if ($persist) {
            $tenant->forceFill([
                'users_count' => $usersCount,
                'modules_enabled_count' => $modulesEnabled,
                'last_tenant_activity_at' => $lastActivity,
                'metrics_cached_at' => now(),
            ])->save();
        }

        return [
            'users_count' => $usersCount,
            'modules_enabled_count' => $modulesEnabled,
            'db_ok' => $dbOk,
            'error' => $error,
            'last_tenant_activity_at' => $lastActivity,
        ];
    }

    /**
     * Refresh metrics for all completed tenants (best-effort).
     */
    public function refreshAll(?int $limit = null): int
    {
        $query = Tenant::query()
            ->where('provisioning_status', 'completed')
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $n = 0;
        foreach ($query->cursor() as $tenant) {
            $this->refresh($tenant, true);
            $n++;
        }

        return $n;
    }
}
