<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\MultiStoreSetupService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SetupTenantMultiStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Tenant $tenant,
        public ?string $defaultStoreName = null
    ) {}

    public function handle(MultiStoreSetupService $service): void
    {
        $this->tenant->refresh();
        $this->tenant->update([
            'multi_store_setup_status' => 'provisioning',
            'multi_store_setup_error' => null,
        ]);

        try {
            $service->setupTenant($this->tenant, $this->defaultStoreName);

            $this->tenant->refresh();
            $this->tenant->update([
                'multi_store_enabled' => true,
                'multi_store_enabled_at' => $this->tenant->multi_store_enabled_at ?: now(),
                'multi_store_setup_status' => 'completed',
                'multi_store_setup_error' => null,
            ]);

            Bus::batch([
                new BackfillTenantStoreDataJob($this->tenant),
            ])->name("multi-store-backfill:{$this->tenant->code}")->dispatch();
        } catch (\Throwable $e) {
            $this->tenant->refresh();
            $this->tenant->update([
                'multi_store_setup_status' => 'failed',
                'multi_store_setup_error' => $e->getMessage(),
            ]);

            Log::error('Multi-store setup failed', [
                'tenant_id' => $this->tenant->id,
                'tenant_code' => $this->tenant->code,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
