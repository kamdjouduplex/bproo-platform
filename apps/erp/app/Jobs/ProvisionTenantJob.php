<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Tenant $tenant,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword
    ) {
        // Use default queue instead of specific queue for easier management
        // Can be changed to 'tenant-provisioning' if you want separate queue
        // $this->onQueue('tenant-provisioning');
    }

    /**
     * Execute the job.
     */
    public function handle(TenantProvisioner $provisioner): void
    {
        // Refresh tenant to get latest data
        $this->tenant->refresh();
        
        // Log the db_name being used
        Log::info("Starting provisioning for tenant {$this->tenant->code}", [
            'tenant_id' => $this->tenant->id,
            'db_name' => $this->tenant->db_name,
            'db_name_from_form' => $this->tenant->db_name,
        ]);
        
        // Mark as provisioning
        $this->tenant->update([
            'provisioning_status' => 'provisioning',
            'provisioning_error' => null,
        ]);

        try {
            // Provision the tenant
            $provisioner->provision(
                $this->tenant,
                $this->adminName,
                $this->adminEmail,
                $this->adminPassword
            );

            if ($this->tenant->multi_store_enabled) {
                Bus::batch([
                    new SetupTenantMultiStoreJob($this->tenant),
                ])->name("multi-store-setup:{$this->tenant->code}")->dispatch();
            }

            // Mark as completed
            $this->tenant->refresh();
            $this->tenant->update([
                'provisioning_status' => 'completed',
                'provisioned_at' => now(),
                'provisioning_error' => null,
            ]);

            Log::info("Tenant {$this->tenant->code} provisioned successfully", [
                'tenant_id' => $this->tenant->id,
                'db_name' => $this->tenant->db_name,
            ]);

        } catch (\Throwable $e) {
            // Mark as failed
            $this->tenant->refresh();
            $this->tenant->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => $e->getMessage(),
            ]);

            Log::error("Tenant {$this->tenant->code} provisioning failed: " . $e->getMessage(), [
                'exception' => $e,
                'tenant_id' => $this->tenant->id,
                'db_name' => $this->tenant->db_name,
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Mark as permanently failed after all retries
        $this->tenant->update([
            'provisioning_status' => 'failed',
            'provisioning_error' => $exception->getMessage(),
        ]);

        Log::error("Tenant {$this->tenant->code} provisioning permanently failed after {$this->tries} attempts", [
            'exception' => $exception,
            'tenant_id' => $this->tenant->id,
        ]);

        // Optionally: Clean up partial provisioning
        $this->cleanupOnFailure();
    }

    /**
     * Clean up partial provisioning on failure.
     * 
     * Note: We don't automatically drop databases for safety.
     * Manual cleanup may be required if provisioning fails.
     */
    private function cleanupOnFailure(): void
    {
        try {
            $dbName = $this->tenant->db_name;
            $connection = \Illuminate\Support\Facades\DB::connection(config('database.default', 'pgsql'));

            $exists = $connection->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$dbName]);
            
            if ($exists) {
                // Log for manual cleanup - we don't auto-delete for safety
                Log::warning("Tenant {$this->tenant->code} database {$dbName} exists but provisioning failed. Manual cleanup may be required.", [
                    'tenant_id' => $this->tenant->id,
                    'tenant_code' => $this->tenant->code,
                    'db_name' => $dbName,
                    'error' => $this->tenant->provisioning_error,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to check cleanup status for tenant {$this->tenant->code}: " . $e->getMessage());
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 30s, 60s, 120s
        return [30, 60, 120];
    }
}
