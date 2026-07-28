<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    private ?Tenant $tenant = null;

    /**
     * Resolve tenant by code. When $requireActive is true (default), only returns active tenants.
     * Set $requireActive = false to resolve for subscription portal (suspended tenants can see status).
     */
    public function resolveByCode(string $code, bool $requireActive = true): ?Tenant
    {
        $tenant = $this->findByCode($code);
        if (!$tenant) {
            return null;
        }
        if ($requireActive && !$tenant->is_active) {
            return null;
        }
        return $tenant;
    }

    /**
     * Find tenant by code regardless of is_active (for distinguishing "not found" vs "deactivated").
     */
    public function findByCode(string $code): ?Tenant
    {
        return Tenant::query()
            ->where('code', $code)
            ->first();
    }

    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;

        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }
}
