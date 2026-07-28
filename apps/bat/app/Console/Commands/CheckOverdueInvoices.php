<?php

namespace App\Console\Commands;

use App\Events\InvoiceOverdue;
use App\Models\Tenant;
use App\Services\TenantManager;
use InovCom\Facturation\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly command that scans all tenants for overdue invoices and:
 * 1. Transitions their status to 'overdue' via the state machine
 * 2. Fires InvoiceOverdue event (listeners can send reminders)
 * 3. Increments reminder_count for dunning escalation
 *
 * Schedule: daily at 07:00 (see Console/Kernel.php)
 */
class CheckOverdueInvoices extends Command
{
    protected $signature   = 'invoices:check-overdue {--tenant= : Run only for a specific tenant code}';
    protected $description = 'Mark sent invoices as overdue and trigger dunning notifications';

    public function handle(TenantManager $tenantManager): int
    {
        $tenantCode = $this->option('tenant');

        $query = Tenant::where('is_active', true)
            ->where('provisioning_status', 'completed');

        if ($tenantCode) {
            $query->where('code', $tenantCode);
        }

        $tenants = $query->get();
        $totalProcessed = 0;

        foreach ($tenants as $tenant) {
            try {
                $count = $this->processTenant($tenant, $tenantManager);
                $totalProcessed += $count;
                if ($count > 0) {
                    $this->line("  <fg=yellow>{$tenant->code}</>: {$count} invoice(s) marquée(s) en retard.");
                }
            } catch (\Throwable $e) {
                Log::error("CheckOverdueInvoices: failed for tenant {$tenant->code}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("  Erreur pour {$tenant->code}: {$e->getMessage()}");
            }
        }

        $this->info("Terminé. {$totalProcessed} facture(s) en retard traitée(s).");
        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant, TenantManager $tenantManager): int
    {
        $tenantManager->setTenant($tenant);

        // Find sent invoices whose due_date is in the past.
        $overdueInvoices = Invoice::on('tenant')
            ->where('status', 'sent')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($overdueInvoices as $invoice) {
            try {
                // Use transitionTo() — writes audit log + fires WorkflowTransitioned.
                if ($invoice->canTransitionTo('overdue')) {
                    $invoice->transitionTo('overdue', null, 'Échéance dépassée (tâche planifiée)');
                }

                // Increment dunning counter.
                Invoice::where('id', $invoice->id)->increment('reminder_count');
                Invoice::where('id', $invoice->id)->update(['last_reminder_at' => now()]);

                // Fire domain event for dunning listeners.
                InvoiceOverdue::dispatch($invoice->fresh());

                $count++;
            } catch (\Throwable $e) {
                Log::warning("CheckOverdueInvoices: failed for invoice #{$invoice->id} (tenant {$tenant->code})", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Reset tenant connection.
        DB::purge('tenant');

        return $count;
    }
}
