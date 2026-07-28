<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreContextService
{
    public function currentStoreId(): ?int
    {
        $value = request()->attributes->get('store_id') ?? session('tenant_store_id');

        return $value !== null ? (int) $value : null;
    }

    public function activeStores(Tenant $tenant): Collection
    {
        if (!$tenant->multi_store_enabled || !Schema::connection('tenant')->hasTable('stores')) {
            return collect();
        }

        return DB::connection('tenant')
            ->table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
    }

    public function defaultStoreId(?Tenant $tenant = null): ?int
    {
        $tenant = $tenant ?: app(TenantManager::class)->tenant();
        if (!$tenant) {
            return null;
        }

        $setting = $tenant->getSetting('default_store_id');
        if ($setting !== null && ctype_digit((string) $setting)) {
            return (int) $setting;
        }

        if (!Schema::connection('tenant')->hasTable('stores')) {
            return null;
        }

        $default = DB::connection('tenant')->table('stores')->where('is_default', true)->value('id');
        if ($default) {
            return (int) $default;
        }

        $first = DB::connection('tenant')->table('stores')->orderBy('id')->value('id');

        return $first ? (int) $first : null;
    }
}
