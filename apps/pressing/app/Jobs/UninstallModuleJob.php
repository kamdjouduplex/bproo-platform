<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UninstallModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $moduleKey,
        public ?int $performedBy = null
    ) {}

    public function handle(ModuleRegistry $registry): void
    {
        $pendingKey = 'module_pending:' . $this->tenantId . ':' . $this->moduleKey;

        try {
            $tenant = Tenant::find($this->tenantId);
            $module = Module::where('key', $this->moduleKey)->first();

            if (!$tenant || !$module) {
                return;
            }

            $registry->uninstall($this->moduleKey, $tenant, $this->performedBy);

            // Set pivot to enabled=false (keep record)
            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['enabled' => false],
            ]);

            $registry->clearCache($tenant);
        } finally {
            Cache::forget($pendingKey);
        }
    }
}
