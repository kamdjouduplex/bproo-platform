<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InstallModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 20;
    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public string $moduleKey
    ) {}

    public function handle(ModuleRegistry $registry): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            Log::warning("InstallModuleJob: tenant {$this->tenantId} not found.");
            $this->clearPending();
            return;
        }

        $module = Module::where('key', $this->moduleKey)->first();
        if (!$module) {
            Log::warning("InstallModuleJob: module {$this->moduleKey} not found.");
            $this->clearPending();
            return;
        }

        try {
            $registry->install($this->moduleKey, $tenant);
            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['enabled' => true],
            ]);
            $registry->clearCache($tenant);
            Log::info("Module {$this->moduleKey} installed for tenant {$tenant->code}");
        } catch (\Throwable $e) {
            Log::error("InstallModuleJob failed: " . $e->getMessage(), [
                'tenant_id' => $this->tenantId,
                'module_key' => $this->moduleKey,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->clearPending();
            throw $e;
        }

        $this->clearPending();
    }

    public function failed(\Throwable $exception): void
    {
        $this->clearPending();
        $tenant = Tenant::find($this->tenantId);
        $module = Module::where('key', $this->moduleKey)->first();
        if ($tenant && $module) {
            $tenant->modules()->syncWithoutDetaching([$module->id => ['enabled' => false]]);
            app(ModuleRegistry::class)->clearCache($tenant);
        }
        Log::error("InstallModuleJob permanently failed", [
            'tenant_id' => $this->tenantId,
            'module_key' => $this->moduleKey,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function clearPending(): void
    {
        Cache::forget("module_job:tenant:{$this->tenantId}:{$this->moduleKey}");
    }
}
