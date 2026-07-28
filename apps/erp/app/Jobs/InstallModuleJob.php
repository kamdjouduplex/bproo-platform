<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use App\Services\ModulesCatalogSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InstallModuleJob implements ShouldQueue
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
            if (!$tenant) {
                return;
            }

            // Ensure Module record exists (create from config if missing, e.g. when modules:sync was not run)
            $module = Module::where('key', $this->moduleKey)->first();
            if (!$module) {
                ModulesCatalogSync::syncFromConfig(preserveDefaults: true);
                $module = Module::where('key', $this->moduleKey)->first();
                if (!$module) {
                    return;
                }
            }

            // Install first (migrations, lifecycle); then attach so sidebar only shows when install succeeded
            $registry->install($this->moduleKey, $tenant, $this->performedBy);

            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['enabled' => true],
            ]);

            // Invalidate after pivot update (install() clears cache before enabled=true is saved).
            $registry->clearCache($tenant);
        } finally {
            Cache::forget($pendingKey);
        }
    }
}
