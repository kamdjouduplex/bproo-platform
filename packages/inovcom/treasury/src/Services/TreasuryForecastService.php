<?php

namespace InovCom\Treasury\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Treasury\Models\TreasuryCommitment;
use InovCom\Treasury\Support\TreasuryRecurrence;
use InovCom\Treasury\Support\TreasuryUrgency;

class TreasuryForecastService
{
    public function __construct(private TreasurySettings $settings)
    {
    }

    /**
     * @return array{
     *     outflows: list<array<string, mixed>>,
     *     inflows: list<array<string, mixed>>,
     *     kpis: array<string, float|int>,
     *     thresholds: array{urgent: int, upcoming: int, alert: int}
     * }
     */
    public function build(?Carbon $today = null, int $horizonDays = 90): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $from = $today->copy()->subDays(120);
        $to = $today->copy()->addDays(max(7, $horizonDays));

        $outflows = collect()
            ->concat($this->manualOutflows($from, $to, $today))
            ->concat($this->expenseOutflows($today))
            ->concat($this->payrollOutflows($today))
            ->sortBy('due_date')
            ->values();

        $inflows = collect()
            ->concat($this->debtInflows($today))
            ->concat($this->invoiceInflows($today))
            ->sortBy('due_date')
            ->values();

        return [
            'outflows' => $outflows->all(),
            'inflows' => $inflows->all(),
            'kpis' => $this->kpis($outflows, $today),
            'thresholds' => [
                'urgent' => $this->settings->urgentDays(),
                'upcoming' => $this->settings->upcomingDays(),
                'alert' => $this->settings->alertDays(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function alertItems(?Carbon $today = null): array
    {
        $forecast = $this->build($today, 90);
        $alertDays = $this->settings->alertDays();

        return array_values(array_filter(
            $forecast['outflows'],
            fn (array $row) => in_array($row['urgency']['key'], [TreasuryUrgency::OVERDUE, TreasuryUrgency::URGENT], true)
                || ((int) $row['urgency']['days'] >= 0 && (int) $row['urgency']['days'] <= $alertDays)
        ));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $outflows
     * @return array<string, float|int>
     */
    private function kpis(Collection $outflows, Carbon $today): array
    {
        $open = $outflows->reject(fn (array $row) => $row['paid']);

        $sumWithin = function (int $days) use ($open, $today) {
            $end = $today->copy()->addDays($days);
            return round((float) $open
                ->filter(fn (array $row) => $row['due_date']->lte($end))
                ->sum('amount'), 2);
        };

        return [
            'due_7' => $sumWithin(7),
            'due_30' => $sumWithin(30),
            'due_60' => $sumWithin(60),
            'due_90' => $sumWithin(90),
            'overdue_count' => $open->where('urgency.key', TreasuryUrgency::OVERDUE)->count(),
            'overdue_amount' => round((float) $open->where('urgency.key', TreasuryUrgency::OVERDUE)->sum('amount'), 2),
            'urgent_count' => $open->where('urgency.key', TreasuryUrgency::URGENT)->count(),
            'paid_count' => $outflows->where('paid', true)->count(),
            'open_count' => $open->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function manualOutflows(Carbon $from, Carbon $to, Carbon $today): Collection
    {
        if (!Schema::connection('tenant')->hasTable('treasury_commitments')) {
            return collect();
        }

        $rows = collect();
        $commitments = TreasuryCommitment::query()
            ->with('provider')
            ->where('status', '!=', TreasuryCommitment::STATUS_CANCELLED)
            ->get();

        foreach ($commitments as $commitment) {
            $paid = $commitment->status === TreasuryCommitment::STATUS_PAID;
            $dates = TreasuryRecurrence::dates(
                $commitment->frequency,
                $commitment->due_date->copy(),
                $from,
                $to,
                $paid ? [$commitment->due_date->toDateString()] : $commitment->paidDates()
            );

            foreach ($dates as $date) {
                $isPaid = $paid || in_array($date->toDateString(), $commitment->paidDates(), true);
                $rows->push($this->item(
                    source: 'manual',
                    sourceId: (int) $commitment->id,
                    label: $commitment->label,
                    category: $commitment->category ?: 'autre',
                    amount: (float) $commitment->amount,
                    dueDate: $date,
                    today: $today,
                    paid: $isPaid,
                    beneficiary: $commitment->beneficiary ?: $commitment->provider?->name,
                    account: $commitment->account_code,
                    frequency: $commitment->frequency,
                    comment: $commitment->comment,
                    editable: true
                ));
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function expenseOutflows(Carbon $today): Collection
    {
        if (!class_exists(\InovCom\Expenses\Models\Expense::class)
            || !Schema::connection('tenant')->hasTable('expenses')) {
            return collect();
        }

        return \InovCom\Expenses\Models\Expense::query()
            ->with('category')
            ->whereIn('status', ['approved', 'pending'])
            ->get()
            ->map(fn ($expense) => $this->item(
                source: 'expense',
                sourceId: (int) $expense->id,
                label: $expense->description ?: ($expense->reference ?: 'Dépense'),
                category: $expense->category?->name ?? 'autre',
                amount: (float) $expense->amount,
                dueDate: $expense->expense_date->copy(),
                today: $today,
                paid: false,
                beneficiary: null,
                account: null,
                frequency: TreasuryRecurrence::ONCE,
                comment: 'Dépense ' . ($expense->status === 'pending' ? 'en attente' : 'approuvée'),
                editable: false,
                urlName: 'tenant.expenses.edit',
                urlParams: ['expense' => $expense->id]
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function payrollOutflows(Carbon $today): Collection
    {
        if (!class_exists(\InovCom\Payroll\Models\PayrollRun::class)
            || !Schema::connection('tenant')->hasTable('payroll_runs')) {
            return collect();
        }

        return \InovCom\Payroll\Models\PayrollRun::query()
            ->where('status', \InovCom\Payroll\Models\PayrollRun::STATUS_PROCESSED)
            ->get()
            ->map(fn ($run) => $this->item(
                source: 'payroll',
                sourceId: (int) $run->id,
                label: 'Salaires ' . ($run->reference ?: $run->period_end?->format('m/Y')),
                category: 'salaire',
                amount: (float) $run->total_net,
                dueDate: ($run->period_end ?? $today)->copy(),
                today: $today,
                paid: false,
                beneficiary: null,
                account: null,
                frequency: TreasuryRecurrence::ONCE,
                comment: 'Paie traitée, non payée',
                editable: false
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function debtInflows(Carbon $today): Collection
    {
        if (!class_exists(\InovCom\Debts\Models\Debt::class)
            || !Schema::connection('tenant')->hasTable('debts')) {
            return collect();
        }

        return \InovCom\Debts\Models\Debt::query()
            ->with('client')
            ->whereIn('status', ['open', 'partial', 'overdue'])
            ->where('balance', '>', 0.01)
            ->get()
            ->map(fn ($debt) => $this->item(
                source: 'debt',
                sourceId: (int) $debt->id,
                label: 'Créance ' . ($debt->reference ?: '#' . $debt->id),
                category: 'creance',
                amount: (float) $debt->balance,
                dueDate: ($debt->due_date ?? $today)->copy(),
                today: $today,
                paid: false,
                beneficiary: $debt->client?->name,
                account: null,
                frequency: TreasuryRecurrence::ONCE,
                comment: $debt->description,
                editable: false,
                urlName: 'tenant.debts.edit',
                urlParams: ['debt' => $debt->id]
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function invoiceInflows(Carbon $today): Collection
    {
        if (!class_exists(\InovCom\Invoicing\Models\Invoice::class)
            || !Schema::connection('tenant')->hasTable('invoices')) {
            return collect();
        }

        return \InovCom\Invoicing\Models\Invoice::query()
            ->with('client')
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01)
            ->get()
            ->map(fn ($invoice) => $this->item(
                source: 'invoice',
                sourceId: (int) $invoice->id,
                label: 'Facture ' . $invoice->invoice_number,
                category: 'facture',
                amount: (float) $invoice->balance,
                dueDate: ($invoice->due_date ?? $invoice->invoice_date ?? $today)->copy(),
                today: $today,
                paid: false,
                beneficiary: $invoice->client?->name,
                account: null,
                frequency: TreasuryRecurrence::ONCE,
                comment: null,
                editable: false,
                urlName: 'tenant.invoicing.edit',
                urlParams: ['invoice' => $invoice->id]
            ));
    }

    /**
     * @param  array<string, mixed>|null  $urlParams
     * @return array<string, mixed>
     */
    private function item(
        string $source,
        int $sourceId,
        string $label,
        ?string $category,
        float $amount,
        Carbon $dueDate,
        Carbon $today,
        bool $paid,
        ?string $beneficiary,
        ?string $account,
        string $frequency,
        ?string $comment,
        bool $editable,
        ?string $urlName = null,
        ?array $urlParams = null
    ): array {
        $urgency = TreasuryUrgency::classify(
            $dueDate,
            $today,
            $this->settings->urgentDays(),
            $this->settings->upcomingDays(),
            $paid
        );

        return [
            'key' => $source . ':' . $sourceId . ':' . $dueDate->toDateString(),
            'source' => $source,
            'source_id' => $sourceId,
            'label' => $label,
            'category' => $category,
            'amount' => round($amount, 2),
            'due_date' => $dueDate,
            'paid' => $paid,
            'beneficiary' => $beneficiary,
            'account' => $account,
            'frequency' => $frequency,
            'comment' => $comment,
            'editable' => $editable,
            'url_name' => $urlName,
            'url_params' => $urlParams,
            'urgency' => $urgency,
        ];
    }
}
