<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\StoreContextService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillTenantStoreDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public Tenant $tenant) {}

    public function handle(StoreContextService $storeContext): void
    {
        config(['database.connections.tenant' => $this->tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $defaultStoreId = $storeContext->defaultStoreId($this->tenant);
        if (!$defaultStoreId) {
            return;
        }

        foreach (['sales', 'expenses', 'loss_records', 'stock_movements', 'stock_levels'] as $table) {
            if (!Schema::connection('tenant')->hasTable($table) || !Schema::connection('tenant')->hasColumn($table, 'store_id')) {
                continue;
            }

            DB::connection('tenant')
                ->table($table)
                ->whereNull('store_id')
                ->update(['store_id' => $defaultStoreId]);
        }

        if (Schema::connection('tenant')->hasTable('users') && Schema::connection('tenant')->hasColumn('users', 'assigned_store_id')) {
            DB::connection('tenant')
                ->table('users')
                ->whereNull('assigned_store_id')
                ->update(['assigned_store_id' => $defaultStoreId]);
        }
    }
}
