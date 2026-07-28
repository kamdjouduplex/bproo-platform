<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantManager;
use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Maintenance\Services\MaintenanceCycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily command — auto-generates preventive maintenance orders from active contracts.
 *
 * Uses intervention_frequency (visits) — distinct from billing_cycle (invoicing).
 *
 * Schedule: daily at 06:00 (see Console/Kernel.php)
 */
class GeneratePreventiveOrders extends Command
{
    protected $signature   = 'maintenance:generate-preventive {--tenant= : Run only for a specific tenant code}';
    protected $description = 'Auto-generate preventive maintenance orders from active contracts';

    public function handle(TenantManager $tenantManager): int
    {
        $tenantCode = $this->option('tenant');

        $query = Tenant::where('is_active', true)
            ->where('provisioning_status', 'completed');

        if ($tenantCode) {
            $query->where('code', $tenantCode);
        }

        $totalCreated = 0;

        foreach ($query->get() as $tenant) {
            try {
                $count = $this->processTenant($tenant, $tenantManager);
                $totalCreated += $count;
                if ($count > 0) {
                    $this->line("  <fg=green>{$tenant->code}</>: {$count} ordre(s) préventif(s) créé(s).");
                }
            } catch (\Throwable $e) {
                Log::error("GeneratePreventiveOrders: failed for tenant {$tenant->code}", ['error' => $e->getMessage()]);
                $this->error("  Erreur pour {$tenant->code}: {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$totalCreated} ordre(s) préventif(s) créé(s).");
        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant, TenantManager $tenantManager): int
    {
        $tenantManager->setTenant($tenant);

        $cycleService = app(MaintenanceCycleService::class);

        $contracts = MaintenanceContract::on('tenant')
            ->where('status', 'active')
            ->where('auto_generate_orders', true)
            ->whereIn('type', ['preventive', 'full_service'])
            ->get();

        $count = 0;
        foreach ($contracts as $contract) {
            try {
                if ($cycleService->shouldAutoGenerate($contract)) {
                    $cycleService->planPreventiveOrder($contract);
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning("GeneratePreventiveOrders: failed for contract #{$contract->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::purge('tenant');
        return $count;
    }
}
