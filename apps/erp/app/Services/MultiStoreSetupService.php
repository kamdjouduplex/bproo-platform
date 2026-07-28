<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MultiStoreSetupService
{
    public function setupTenant(Tenant $tenant, ?string $defaultStoreName = null): void
    {
        $this->ensureTenantConnection($tenant);
        $this->ensureStoresTable();
        $this->ensureTransactionStoreColumns();
        $storeId = $this->ensureDefaultStore($defaultStoreName ?: 'Magasin principal');
        $this->ensureStoreSettings($tenant, $storeId);
    }

    private function ensureTenantConnection(Tenant $tenant): void
    {
        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function ensureStoresTable(): void
    {
        if (Schema::connection('tenant')->hasTable('stores')) {
            return;
        }

        Schema::connection('tenant')->create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function ensureDefaultStore(string $defaultStoreName): int
    {
        $defaultStore = DB::connection('tenant')
            ->table('stores')
            ->where('is_default', true)
            ->first();

        if ($defaultStore) {
            return (int) $defaultStore->id;
        }

        $firstStore = DB::connection('tenant')->table('stores')->orderBy('id')->first();
        if ($firstStore) {
            DB::connection('tenant')->table('stores')->where('id', $firstStore->id)->update([
                'is_default' => true,
                'updated_at' => now(),
            ]);

            return (int) $firstStore->id;
        }

        return (int) DB::connection('tenant')->table('stores')->insertGetId([
            'name' => $defaultStoreName,
            'code' => Str::upper(Str::limit(Str::slug($defaultStoreName, ''), 12, '')) ?: 'MAIN',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureStoreSettings(Tenant $tenant, int $defaultStoreId): void
    {
        $tenant->setSetting('multi_store_enabled', '1');
        $tenant->setSetting('default_store_id', (string) $defaultStoreId);
    }

    private function ensureTransactionStoreColumns(): void
    {
        foreach (['sales', 'expenses', 'loss_records', 'stock_movements'] as $table) {
            if (!Schema::connection('tenant')->hasTable($table) || Schema::connection('tenant')->hasColumn($table, 'store_id')) {
                continue;
            }

            Schema::connection('tenant')->table($table, function ($blueprint) {
                $blueprint->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }

        if (Schema::connection('tenant')->hasTable('stock_levels') && !Schema::connection('tenant')->hasColumn('stock_levels', 'store_id')) {
            Schema::connection('tenant')->table('stock_levels', function ($blueprint) {
                $blueprint->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }
    }
}
