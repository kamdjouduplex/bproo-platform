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
use Illuminate\Support\Facades\DB;
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
        $central = $this->centralConnectionName();

        try {
            $tenant = Tenant::on($central)->find($this->tenantId);
            if (!$tenant) {
                return;
            }

            // Ensure Module record exists (create from config if missing, e.g. when modules:sync was not run)
            $module = Module::on($central)->where('key', $this->moduleKey)->first();
            if (!$module) {
                ModulesCatalogSync::syncFromConfig(preserveDefaults: true);
                $module = Module::on($central)->where('key', $this->moduleKey)->first();
                if (!$module) {
                    return;
                }
            }

            // Install first (migrations, lifecycle); then attach so sidebar only shows when install succeeded
            $registry->install($this->moduleKey, $tenant, $this->performedBy);

            $tenant->setConnection($central);
            $tenant->modules()->syncWithoutDetaching([
                $module->id => ['enabled' => true],
            ]);

            // Invalidate after pivot update (install() clears cache before enabled=true is saved).
            $registry->clearCache($tenant);
        } finally {
            DB::setDefaultConnection($central);
            config(['database.default' => $central]);
            Cache::forget($pendingKey);
        }
    }

    private function centralConnectionName(): string
    {
        $snapshot = config('database.central');
        if (is_string($snapshot) && $snapshot !== '' && $snapshot !== 'tenant') {
            return $snapshot;
        }

        $current = DB::getDefaultConnection();
        if (is_string($current) && $current !== '' && $current !== 'tenant') {
            return $current;
        }

        return 'pgsql';
    }
}
