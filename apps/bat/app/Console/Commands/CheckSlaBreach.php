<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\SlaBreachWarning;
use App\Services\TenantManager;
use InovCom\Maintenance\Models\MaintenanceOrder;
use InovCom\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hourly command — finds maintenance orders whose SLA deadline is within 2 hours.
 * Sends a SlaBreachWarning database notification to the assigned technician.
 *
 * Schedule: every hour (see Console/Kernel.php)
 */
class CheckSlaBreach extends Command
{
    protected $signature   = 'maintenance:check-sla {--tenant= : Run only for a specific tenant code}';
    protected $description = 'Warn about imminent SLA breaches on open maintenance orders';

    public function handle(TenantManager $tenantManager): int
    {
        $tenantCode = $this->option('tenant');

        $query = Tenant::where('is_active', true)
            ->where('provisioning_status', 'completed');

        if ($tenantCode) {
            $query->where('code', $tenantCode);
        }

        $totalWarned = 0;

        foreach ($query->get() as $tenant) {
            try {
                $count = $this->processTenant($tenant, $tenantManager);
                $totalWarned += $count;
                if ($count > 0) {
                    $this->line("  <fg=yellow>{$tenant->code}</>: {$count} alerte(s) SLA envoyée(s).");
                }
            } catch (\Throwable $e) {
                Log::error("CheckSlaBreach: failed for tenant {$tenant->code}", ['error' => $e->getMessage()]);
                $this->error("  Erreur pour {$tenant->code}: {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$totalWarned} alerte(s) SLA émise(s).");
        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant, TenantManager $tenantManager): int
    {
        $tenantManager->setTenant($tenant);

        $warningWindow = now()->addHours(2);

        // Orders that are open/assigned/in_progress, have a due_at, and breach within 2h
        $orders = MaintenanceOrder::on('tenant')
            ->whereIn('status', ['open', 'assigned', 'in_progress'])
            ->whereNotNull('due_at')
            ->whereNotNull('assigned_to')
            ->where('due_at', '>', now())          // not yet breached
            ->where('due_at', '<=', $warningWindow) // but within 2h
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $technician = User::on('tenant')->find($order->assigned_to);
                if ($technician) {
                    $hoursRemaining = now()->diffInMinutes($order->due_at) / 60;
                    $technician->notify(new SlaBreachWarning($order, $hoursRemaining));
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning("CheckSlaBreach: failed for order #{$order->id}", ['error' => $e->getMessage()]);
            }
        }

        DB::purge('tenant');
        return $count;
    }
}
