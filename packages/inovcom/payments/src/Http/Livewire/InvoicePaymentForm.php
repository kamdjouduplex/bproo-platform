<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\InvoicePayments\Models\FiscalWithholdingType;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
use InovCom\InvoicePayments\Support\WithholdingCalculator;
use InovCom\InvoicePayments\Support\WithholdingSchema;
use InovCom\Invoicing\Models\Invoice;
use Livewire\Component;

class InvoicePaymentForm extends Component
{
    public Invoice $invoice;

    public string $amount = '';
    public string $payment_date = '';
    public string $payment_method = 'cash';
    public ?string $notes = null;
    public ?string $external_reference = null;

    /** @var list<array{type_id: int|null, type_name: string, base_amount: string, rate: string, amount: string, account_code: string, comment: string}> */
    public array $withholdings = [];

    public ?int $cancellingPaymentId = null;
    public string $cancellation_reason = '';

    public ?int $targetScheduleId = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['client', 'lines', 'schedules']);

        if (!$this->can('invoice_payments.receive')) {
            abort(403);
        }

        $this->payment_date = now()->format('Y-m-d');
        WithholdingSchema::ensure();

        $scheduleId = (int) request()->query('schedule', 0);
        if ($scheduleId > 0 && $this->applyScheduleTarget($scheduleId)) {
            return;
        }

        $this->resetAmountToBalance();
    }

    /**
     * Prépare un encaissement calé sur une échéance précise.
     */
    private function applyScheduleTarget(int $scheduleId): bool
    {
        $schedule = $this->invoice->schedules->firstWhere('id', $scheduleId);
        if (!$schedule || $schedule->isPaid()) {
            return false;
        }

        $remaining = min($schedule->remaining(), max(0, (float) $this->invoice->balance));
        if ($remaining <= 0.01) {
            return false;
        }

        $this->targetScheduleId = $schedule->id;
        $this->amount = (string) WithholdingCalculator::roundMoney($remaining);
        $this->notes = 'Échéance n°' . $schedule->installment_number . ' du ' . $schedule->due_date->format('d/m/Y');
        $this->syncCashFromWithholdings();

        return true;
    }

    public function resetAmountToBalance(): void
    {
        $this->invoice->refresh();
        $this->syncCashFromWithholdings();
    }

    public function payFullBalance(): void
    {
        $this->targetScheduleId = null;
        $this->resetAmountToBalance();
    }

    public function paySchedule(int $scheduleId): void
    {
        $this->invoice->refresh();
        $this->invoice->load('schedules');
        if (!$this->applyScheduleTarget($scheduleId)) {
            session()->flash('error', 'Cette échéance n’est plus à encaisser.');
        }
    }

    public function save(): void
    {
        if (!$this->can('invoice_payments.receive')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->invoice->refresh();
        $maxBalance = WithholdingCalculator::roundMoney(max(0, (float) $this->invoice->balance));
        $summary = $this->settlementSummary();

        $data = $this->validate([
            'amount' => 'required|numeric|min:0|max:' . max(1, $maxBalance),
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,bank_transfer,mobile_money,other',
            'notes' => 'nullable|string|max:500',
            'external_reference' => 'nullable|string|max:100',
            'withholdings' => 'array',
            'withholdings.*.amount' => 'nullable|numeric|min:0',
            'withholdings.*.base_amount' => 'nullable|numeric|min:0',
            'withholdings.*.rate' => 'nullable|numeric|min:0',
            'withholdings.*.comment' => 'nullable|string|max:500',
            'withholdings.*.account_code' => 'nullable|string|max:50',
        ], [
            'amount.max' => 'Le montant encaissé ne peut pas dépasser le solde restant (' . fmt_money($maxBalance) . ' FCFA).',
        ]);

        if ($summary['exceeds']) {
            session()->flash('error', 'Montant encaissé + retenues dépasse le solde de la facture (' . fmt_money($maxBalance) . ' FCFA).');
            return;
        }

        if ($summary['settled'] <= 0) {
            session()->flash('error', 'Indiquez un montant encaissé ou au moins une retenue fiscale.');
            return;
        }

        if (!$this->invoice->canReceivePayment()) {
            session()->flash('error', 'Cette facture est soldée ou n\'accepte plus d\'encaissement.');
            return;
        }

        try {
            $payment = app(InvoicePaymentsService::class)->recordPayment(
                $this->invoice->id,
                (float) $data['amount'],
                $data['payment_date'],
                $data['payment_method'],
                $data['notes'] ?? null,
                $data['external_reference'] ?? null,
                null,
                $this->normalizedWithholdings()
            );

            session()->flash('success', 'Encaissement enregistré : ' . $payment->reference);

            $this->redirect(route('tenant.invoice_payments.receipt.print', [
                'invoicePayment' => $payment->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function startCancel(int $paymentId): void
    {
        $this->cancellingPaymentId = $paymentId;
        $this->cancellation_reason = '';
    }

    public function cancelCancel(): void
    {
        $this->cancellingPaymentId = null;
        $this->cancellation_reason = '';
    }

    public function confirmCancel(): void
    {
        if (!$this->can('invoice_payments.cancel')) {
            session()->flash('error', 'Permission refusée pour annuler un encaissement.');
            return;
        }

        $this->validate([
            'cancellation_reason' => 'required|string|min:3|max:500',
        ], [
            'cancellation_reason.required' => 'Indiquez le motif d\'annulation.',
        ]);

        try {
            app(InvoicePaymentsService::class)->cancelPayment(
                (int) $this->cancellingPaymentId,
                $this->cancellation_reason
            );
            $this->cancellingPaymentId = null;
            $this->cancellation_reason = '';
            $this->invoice->refresh();
            $this->resetAmountToBalance();
            session()->flash('success', 'Encaissement annulé. La facture a été recalculée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $this->invoice->refresh();
        $this->invoice->load('schedules');

        if ($this->invoice->schedules->isNotEmpty()
            && class_exists(\InovCom\Invoicing\Services\InvoiceScheduleService::class)) {
            app(\InovCom\Invoicing\Services\InvoiceScheduleService::class)
                ->refreshOverdueStatuses($this->invoice);
            $this->invoice->load('schedules');
        }

        $payments = InvoicePayment::query()
            ->where('invoice_id', $this->invoice->id)
            ->with(array_filter([
                'creator',
                'canceller',
                ...InvoicePayment::optionalWithholdingsRelation(),
            ]))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $canReceive = $this->invoice->canReceivePayment() && $this->can('invoice_payments.receive');
        $scheduleAmountDueNow = $this->invoice->schedules->isNotEmpty()
            ? app(\InovCom\Invoicing\Services\InvoiceScheduleService::class)->amountCurrentlyDue($this->invoice)
            : null;
        $targetSchedule = $this->targetScheduleId
            ? $this->invoice->schedules->firstWhere('id', $this->targetScheduleId)
            : null;

        $summary = $this->settlementSummary();

        return view('inovcom-invoice-payments::livewire.payment-form')
            ->layout('layouts.app', [
                'title' => 'Encaissement',
                'subtitle' => $this->invoice->invoice_number,
            ])
            ->with([
                'payments' => $payments,
                'canReceive' => $canReceive,
                'canCancel' => $this->can('invoice_payments.cancel'),
                'invoiceSchedules' => $this->invoice->schedules,
                'scheduleAmountDueNow' => $scheduleAmountDueNow,
                'targetSchedule' => $targetSchedule,
                'withholdingTypes' => $this->activeWithholdingTypes(),
                'settlement' => $summary,
                'canManageWithholdings' => $this->can('invoice_payments.manage_withholdings'),
            ]);
    }

    public function addWithholding(?int $typeId = null): void
    {
        $type = $typeId ? $this->activeWithholdingTypes()->firstWhere('id', $typeId) : null;
        $base = WithholdingCalculator::roundMoney((float) $this->settlementBase());
        $rate = $type ? (float) $type->default_rate : 0.0;

        $this->withholdings[] = [
            'type_id' => $type?->id,
            'type_name' => $type?->name ?? '',
            'base_amount' => (string) $base,
            'rate' => $rate > 0 ? (string) $rate : '',
            'amount' => (string) WithholdingCalculator::amountFromBaseAndRate($base, $rate),
            'account_code' => (string) ($type?->default_account ?? ''),
            'comment' => '',
        ];
        $this->syncCashFromWithholdings();
    }

    public function removeWithholding(int $index): void
    {
        unset($this->withholdings[$index]);
        $this->withholdings = array_values($this->withholdings);
        $this->syncCashFromWithholdings();
    }

    public function updatedWithholdings($value, $key): void
    {
        if (!preg_match('/^(\d+)\.(type_id|base_amount|rate|amount)$/', (string) $key, $m)) {
            return;
        }

        $index = (int) $m[1];
        $field = $m[2];
        if (!isset($this->withholdings[$index])) {
            return;
        }

        if ($field === 'type_id') {
            $type = $this->activeWithholdingTypes()->firstWhere('id', (int) $this->withholdings[$index]['type_id']);
            if ($type) {
                $this->withholdings[$index]['type_name'] = $type->name;
                if ((float) $type->default_rate > 0) {
                    $this->withholdings[$index]['rate'] = (string) $type->default_rate;
                }
                if (($this->withholdings[$index]['account_code'] ?? '') === '') {
                    $this->withholdings[$index]['account_code'] = (string) ($type->default_account ?? '');
                }
            }
        }

        if (in_array($field, ['type_id', 'base_amount', 'rate'], true)) {
            $base = WithholdingCalculator::roundMoney((float) ($this->withholdings[$index]['base_amount'] ?? 0));
            $this->withholdings[$index]['base_amount'] = (string) $base;
            $rate = (float) ($this->withholdings[$index]['rate'] ?? 0);
            if ($rate > 0) {
                $this->withholdings[$index]['amount'] = (string) WithholdingCalculator::amountFromBaseAndRate($base, $rate);
            }
        }

        $this->withholdings[$index]['amount'] = (string) WithholdingCalculator::roundMoney(
            (float) ($this->withholdings[$index]['amount'] ?? 0)
        );
        $this->syncCashFromWithholdings();
    }

    /**
     * @return \Illuminate\Support\Collection<int, FiscalWithholdingType>
     */
    private function activeWithholdingTypes()
    {
        if (!Schema::connection('tenant')->hasTable('fiscal_withholding_types')) {
            return collect();
        }

        FiscalWithholdingType::syncDefaults();

        return FiscalWithholdingType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{invoice_total: float, cash_received: float, withholding_total: float, settled: float, balance_before: float, remaining: float, exceeds: bool}
     */
    private function settlementSummary(): array
    {
        return WithholdingCalculator::summarize(
            (float) $this->invoice->total,
            (float) $this->invoice->amount_paid,
            (float) $this->amount,
            $this->normalizedWithholdings()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedWithholdings(): array
    {
        $rows = [];
        foreach ($this->withholdings as $row) {
            $amount = WithholdingCalculator::roundMoney((float) ($row['amount'] ?? 0));
            if ($amount <= 0) {
                continue;
            }
            $rows[] = [
                'type_id' => isset($row['type_id']) && $row['type_id'] ? (int) $row['type_id'] : null,
                'type_name' => $row['type_name'] ?? '',
                'base_amount' => WithholdingCalculator::roundMoney((float) ($row['base_amount'] ?? 0)),
                'rate' => (float) ($row['rate'] ?? 0),
                'amount' => $amount,
                'account_code' => $row['account_code'] ?? null,
                'comment' => $row['comment'] ?? null,
            ];
        }

        return $rows;
    }

    private function settlementBase(): float
    {
        $balance = max(0, (float) $this->invoice->balance);
        if (!$this->targetScheduleId) {
            return $balance;
        }

        $this->invoice->loadMissing('schedules');
        $schedule = $this->invoice->schedules->firstWhere('id', $this->targetScheduleId);
        if (!$schedule) {
            return $balance;
        }

        return min($balance, $schedule->remaining());
    }

    private function syncCashFromWithholdings(): void
    {
        if (!$this->invoice->canReceivePayment()) {
            $this->amount = '';

            return;
        }

        $this->amount = (string) WithholdingCalculator::cashDue(
            $this->settlementBase(),
            $this->normalizedWithholdings()
        );
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
