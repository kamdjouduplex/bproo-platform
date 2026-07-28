<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    private ?Tenant $tenant = null;

    public function resolveByCode(string $code): ?Tenant
    {
        return Tenant::query()
            ->where('code', $code)
            ->where('is_active', true)
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
