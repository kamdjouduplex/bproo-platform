<?php

namespace InovCom\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Payroll\Models\EmployeePayrollAdjustment;
use InovCom\Payroll\Models\PayrollLine;
use InovCom\Payroll\Models\PayrollRun;

class PayrollService
{
    public function __construct(
        private PayrollCalculationService $calculator
    ) {
    }

    public function nextReference(): string
    {
        $prefix = 'PAY-' . now()->format('Ym') . '-';
        $last = PayrollRun::query()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function createRun(string $periodStart, string $periodEnd, ?string $notes = null): PayrollRun
    {
        $run = PayrollRun::create([
            'reference' => $this->nextReference(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => PayrollRun::STATUS_DRAFT,
            'notes' => $notes,
        ]);

        $this->calculator->buildRunLines($run);
        $this->refreshRunTotals($run);

        return $run->fresh(['lines.employee', 'lines.items']);
    }

    public function saveDraft(PayrollRun $run, string $periodStart, string $periodEnd, ?string $notes): PayrollRun
    {
        if (!$run->isDraft()) {
            throw new \RuntimeException('Seules les fiches en brouillon peuvent être modifiées.');
        }

        return DB::connection('tenant')->transaction(function () use ($run, $periodStart, $periodEnd, $notes) {
            $run->update([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'notes' => $notes,
            ]);

            $this->calculator->buildRunLines($run->fresh());
            $this->refreshRunTotals($run);

            return $run->fresh(['lines.employee', 'lines.items']);
        });
    }

    public function recalculate(PayrollRun $run): PayrollRun
    {
        if (!$run->isDraft()) {
            throw new \RuntimeException('Recalcul impossible : fiche non brouillon.');
        }

        $this->calculator->buildRunLines($run);
        $this->refreshRunTotals($run);

        return $run->fresh(['lines.employee', 'lines.items']);
    }

    public function process(PayrollRun $run, ?int $userId): PayrollRun
    {
        if (!$run->isDraft()) {
            throw new \RuntimeException('Cette fiche a déjà été traitée.');
        }

        $run->update([
            'status' => PayrollRun::STATUS_PROCESSED,
            'processed_at' => now(),
            'processed_by' => $userId,
        ]);

        return $run->fresh();
    }

    public function markAsPaid(PayrollRun $run): PayrollRun
    {
        if (!$run->isProcessed()) {
            throw new \RuntimeException('Traitez la fiche avant de la marquer comme payée.');
        }

        $run->update([
            'status' => PayrollRun::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Supprime une fiche créée par erreur (brouillon ou traitée, jamais payée).
     * Les ajustements employés liés sont libérés pour une future fiche.
     */
    public function cancel(PayrollRun $run): void
    {
        if ($run->isPaid()) {
            throw new \RuntimeException('Impossible d\'annuler une fiche déjà payée.');
        }

        DB::connection('tenant')->transaction(function () use ($run) {
            if (Schema::connection('tenant')->hasTable('employee_payroll_adjustments')) {
                EmployeePayrollAdjustment::query()
                    ->where('payroll_run_id', $run->id)
                    ->update(['payroll_run_id' => null]);
            }

            $run->lines()->delete();
            $run->delete();
        });
    }

    public function refreshRunTotals(PayrollRun $run): void
    {
        $lines = PayrollLine::query()->where('payroll_run_id', $run->id)->get();

        $run->update([
            'total_gross' => $lines->sum(fn ($l) => (float) $l->base_salary + (float) $l->bonuses),
            'total_deductions' => $lines->sum(fn ($l) => (float) $l->deductions),
            'total_net' => $lines->sum(fn ($l) => (float) $l->net_salary),
        ]);
    }

    public function statusLabel(string $status): string
    {
        return PayrollRun::STATUS_LABELS[$status] ?? $status;
    }
}
