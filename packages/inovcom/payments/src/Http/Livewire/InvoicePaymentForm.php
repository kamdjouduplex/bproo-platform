<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InovCom\InvoicePayments\Models\FiscalWithholdingType;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Models\InvoicePaymentAttachment;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
use InovCom\InvoicePayments\Support\InvoiceFiscalBreakdown;
use InovCom\InvoicePayments\Support\WithholdingCalculator;
use InovCom\InvoicePayments\Support\WithholdingKind;
use InovCom\InvoicePayments\Support\WithholdingSchema;
use InovCom\Invoicing\Models\Invoice;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoicePaymentForm extends Component
{
    use WithFileUploads;

    public Invoice $invoice;

    public string $amount = '';
    public string $payment_date = '';
    public string $payment_method = 'cash';
    public ?string $notes = null;
    public ?string $external_reference = null;

    /** @var list<array{type_id: int|null, type_name: string, kind: string, base_amount: string, rate: string, amount: string, account_code: string, comment: string}> */
    public array $withholdings = [];

    public $newCertificate = null;

    /** @var array<int|string, mixed> */
    public array $historyCertificates = [];

    /** @var array<int|string, mixed> */
    public array $replaceCertificates = [];

    public ?int $cancellingPaymentId = null;
    public string $cancellation_reason = '';

    public ?int $targetScheduleId = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['client', 'lines', 'schedules', 'taxLines']);

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
            'newCertificate' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
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

        foreach ($this->normalizedWithholdings() as $row) {
            $kind = WithholdingKind::resolve($row['kind'] ?? null, $row['type_code'] ?? null, $row['type_name'] ?? null);
            $error = InvoiceFiscalBreakdown::fromInvoice($this->invoice)->withholdingError(
                $kind,
                $kind === WithholdingKind::VAT
                    ? InvoiceFiscalBreakdown::alreadyWithheldVat($this->invoice)
                    : ($kind === WithholdingKind::IS ? InvoiceFiscalBreakdown::alreadyWithheldIs($this->invoice) : 0)
            );
            if ($error) {
                session()->flash('error', $error);
                return;
            }
        }

        if (!$this->invoice->canReceivePayment()) {
            session()->flash('error', 'Cette facture est soldée ou n\'accepte plus d\'encaissement.');
            return;
        }

        try {
            $service = app(InvoicePaymentsService::class);
            $payment = $service->recordPayment(
                $this->invoice->id,
                (float) $data['amount'],
                $data['payment_date'],
                $data['payment_method'],
                $data['notes'] ?? null,
                $data['external_reference'] ?? null,
                null,
                $this->normalizedWithholdings()
            );

            if ($this->newCertificate && $this->normalizedWithholdings() !== []) {
                $this->storeCertificateFile($payment, $this->newCertificate);
            }

            session()->flash('success', 'Encaissement enregistré : ' . $payment->reference);

            $this->redirect(route('tenant.invoice_payments.receipt.print', [
                'invoicePayment' => $payment->id,
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function attachHistoryCertificate(int $paymentId): void
    {
        if (!$this->can('invoice_payments.receive') && !$this->can('invoice_payments.view')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $file = $this->historyCertificates[$paymentId] ?? null;
        if (!$file) {
            session()->flash('error', 'Choisissez le fichier de l’attestation de retenue.');
            return;
        }

        $this->validate([
            'historyCertificates.'.$paymentId => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        $payment = InvoicePayment::query()->where('invoice_id', $this->invoice->id)->findOrFail($paymentId);
        if (! $payment->hasSourceWithholding()) {
            session()->flash('error', 'Un justificatif n’est demandé que s’il y a une retenue à la source.');
            return;
        }

        $this->storeCertificateFile($payment, $file);
        unset($this->historyCertificates[$paymentId]);
        session()->flash('success', 'Justificatif de retenue ajouté.');
    }

    public function updatedReplaceCertificates($value, $key = null): void
    {
        $id = (int) $key;
        if ($id > 0 && $value) {
            $this->replaceCertificate($id);
            return;
        }

        foreach ($this->replaceCertificates as $attachmentId => $file) {
            if ($file) {
                $this->replaceCertificate((int) $attachmentId);
            }
        }
    }

    public function replaceCertificate(int $attachmentId): void
    {
        if (! $this->can('invoice_payments.receive')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->validate([
            'replaceCertificates.'.$attachmentId => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        $attachment = InvoicePaymentAttachment::query()->findOrFail($attachmentId);
        $payment = InvoicePayment::query()
            ->where('invoice_id', $this->invoice->id)
            ->findOrFail($attachment->invoice_payment_id);

        if (! $payment->hasSourceWithholding() || ! $payment->isActive()) {
            session()->flash('error', 'Ce justificatif ne peut pas être mis à jour.');
            return;
        }

        try {
            app(InvoicePaymentsService::class)->replaceUploadedCertificate(
                $attachment,
                $this->replaceCertificates[$attachmentId]
            );
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        unset($this->replaceCertificates[$attachmentId]);
        session()->flash('success', 'Justificatif mis à jour.');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if (!$this->can('invoice_payments.receive') && !$this->can('invoice_payments.cancel')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $attachment = InvoicePaymentAttachment::query()->findOrFail($attachmentId);
        $payment = InvoicePayment::query()->where('invoice_id', $this->invoice->id)->findOrFail($attachment->invoice_payment_id);

        app(InvoicePaymentsService::class)->deleteAttachment($attachment);
        unset($payment);

        session()->flash('success', 'Justificatif retiré.');
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
        $this->invoice->load(['schedules', 'taxLines']);

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

        $this->hydratePaymentAttachments($payments);

        $canReceive = $this->invoice->canReceivePayment() && $this->can('invoice_payments.receive');
        $scheduleAmountDueNow = $this->invoice->schedules->isNotEmpty()
            ? app(\InovCom\Invoicing\Services\InvoiceScheduleService::class)->amountCurrentlyDue($this->invoice)
            : null;
        $targetSchedule = $this->targetScheduleId
            ? $this->invoice->schedules->firstWhere('id', $this->targetScheduleId)
            : null;

        $summary = $this->settlementSummary();
        $fiscal = InvoiceFiscalBreakdown::fromInvoice($this->invoice);
        $alreadyVat = InvoiceFiscalBreakdown::alreadyWithheldVat($this->invoice);

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
                'fiscal' => $fiscal,
                'remainingVat' => $fiscal->remainingVat($alreadyVat),
                'remainingIs' => $fiscal->remainingIs(InvoiceFiscalBreakdown::alreadyWithheldIs($this->invoice)),
                'canManageWithholdings' => $this->can('invoice_payments.manage_withholdings'),
            ]);
    }

    public function addWithholding(?int $typeId = null): void
    {
        $type = $typeId ? $this->activeWithholdingTypes()->firstWhere('id', $typeId) : null;
        $kind = $type?->resolvedKind() ?? WithholdingKind::OTHER;
        $error = $this->withholdingGuard($kind);
        if ($error) {
            session()->flash('error', $error);
            return;
        }

        if ($kind === WithholdingKind::VAT) {
            foreach ($this->withholdings as $existing) {
                if (($existing['kind'] ?? '') === WithholdingKind::VAT) {
                    session()->flash('error', 'La TVA de la facture est déjà ajoutée à cet encaissement.');
                    return;
                }
            }
        }

        if ($kind === WithholdingKind::IS) {
            foreach ($this->withholdings as $existing) {
                if (($existing['kind'] ?? '') === WithholdingKind::IS) {
                    session()->flash('error', 'L’IS de la facture est déjà ajouté à cet encaissement.');
                    return;
                }
            }
        }

        $row = $this->suggestedRow($type);
        if ($kind === WithholdingKind::VAT && (float) $row['amount'] <= 0) {
            session()->flash('error', $this->withholdingGuard($kind) ?: 'Cette retenue n’est pas applicable à la facture.');
            return;
        }
        if ($kind === WithholdingKind::IS
            && InvoiceFiscalBreakdown::fromInvoice($this->invoice)->locksIsAmount()
            && (float) $row['amount'] <= 0) {
            session()->flash('error', $this->withholdingGuard($kind) ?: 'Cette retenue n’est pas applicable à la facture.');
            return;
        }

        $this->withholdings[] = $row;
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
            $kind = $type?->resolvedKind() ?? WithholdingKind::OTHER;
            $error = $this->withholdingGuard($kind, $index);
            if ($error) {
                session()->flash('error', $error);
                $this->withholdings[$index]['type_id'] = null;
                $this->withholdings[$index]['kind'] = WithholdingKind::OTHER;
                $this->withholdings[$index]['type_name'] = '';
                return;
            }
            $this->withholdings[$index] = array_merge(
                $this->suggestedRow($type, $index),
                ['comment' => $this->withholdings[$index]['comment'] ?? '']
            );
            $this->syncCashFromWithholdings();
            return;
        }

        $kind = $this->withholdings[$index]['kind'] ?? WithholdingKind::OTHER;
        $locksIs = $kind === WithholdingKind::IS
            && InvoiceFiscalBreakdown::fromInvoice($this->invoice)->locksIsAmount();
        if ($kind === WithholdingKind::VAT || $locksIs) {
            $type = $this->activeWithholdingTypes()->firstWhere('id', (int) ($this->withholdings[$index]['type_id'] ?? 0));
            $this->withholdings[$index] = array_merge(
                $this->suggestedRow($type, $index),
                ['comment' => $this->withholdings[$index]['comment'] ?? '']
            );
            $this->syncCashFromWithholdings();
            return;
        }

        if (in_array($field, ['base_amount', 'rate'], true)) {
            $base = WithholdingCalculator::roundMoney((float) ($this->withholdings[$index]['base_amount'] ?? 0));
            $this->withholdings[$index]['base_amount'] = (string) $base;
            $rate = (float) ($this->withholdings[$index]['rate'] ?? 0);
            if ($rate > 0 && $base > 0) {
                $this->withholdings[$index]['amount'] = (string) WithholdingCalculator::amountFromBaseAndRate($base, $rate);
            }
        }

        $this->withholdings[$index]['amount'] = (string) WithholdingCalculator::roundMoney(
            (float) ($this->withholdings[$index]['amount'] ?? 0)
        );
        $this->syncCashFromWithholdings();
    }

    private function suggestedRow(?FiscalWithholdingType $type, ?int $exceptIndex = null): array
    {
        $fiscal = InvoiceFiscalBreakdown::fromInvoice($this->invoice);
        $kind = $type?->resolvedKind() ?? WithholdingKind::OTHER;
        $remainingBalance = WithholdingCalculator::roundMoney(max(0, (float) $this->invoice->balance));
        $settlementBase = WithholdingCalculator::roundMoney($this->settlementBase());

        $base = $fiscal->ht;
        $rate = $type ? (float) $type->default_rate : 0.0;
        $amount = 0.0;

        if ($kind === WithholdingKind::VAT) {
            $pending = [];
            foreach ($this->withholdings as $i => $row) {
                if ($exceptIndex !== null && $i === $exceptIndex) {
                    continue;
                }
                $pending[] = $row;
            }
            $already = InvoiceFiscalBreakdown::alreadyWithheldVat($this->invoice, $pending);
            $base = $fiscal->ht;
            $rate = $fiscal->vatRate;
            $amount = WithholdingCalculator::suggestInvoiceVatAmount(
                $fiscal->vat,
                $already,
                $settlementBase > 0 ? $settlementBase : $remainingBalance,
                $remainingBalance
            );
        } elseif ($kind === WithholdingKind::IS && $fiscal->locksIsAmount()) {
            $pending = [];
            foreach ($this->withholdings as $i => $row) {
                if ($exceptIndex !== null && $i === $exceptIndex) {
                    continue;
                }
                $pending[] = $row;
            }
            $already = InvoiceFiscalBreakdown::alreadyWithheldIs($this->invoice, $pending);
            $base = $fiscal->ht;
            $rate = $fiscal->isRate;
            $amount = WithholdingCalculator::suggestInvoiceVatAmount(
                $fiscal->is,
                $already,
                $settlementBase > 0 ? $settlementBase : $remainingBalance,
                $remainingBalance
            );
        } elseif ($rate > 0) {
            $amount = WithholdingCalculator::amountFromBaseAndRate($base, $rate);
        }

        return [
            'type_id' => $type?->id,
            'type_name' => $type?->name ?? '',
            'kind' => $kind,
            'base_amount' => $base > 0 ? (string) $base : '',
            'rate' => $rate > 0 ? (string) $rate : '',
            'amount' => $amount > 0 ? (string) $amount : '',
            'account_code' => (string) ($type?->default_account ?? ''),
            'comment' => '',
        ];
    }

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

    private function hydratePaymentAttachments($payments): void
    {
        WithholdingSchema::ensure();
        if ($payments->isEmpty() || ! InvoicePayment::hasAttachmentsTable()) {
            foreach ($payments as $payment) {
                $payment->setRelation('attachments', $payment->newCollection());
            }

            return;
        }

        $grouped = InvoicePaymentAttachment::query()
            ->whereIn('invoice_payment_id', $payments->modelKeys())
            ->orderByDesc('id')
            ->get()
            ->groupBy('invoice_payment_id');

        foreach ($payments as $payment) {
            $payment->setRelation(
                'attachments',
                $grouped->get($payment->id, $payment->newCollection())
            );
        }
    }

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
                'kind' => $row['kind'] ?? WithholdingKind::OTHER,
                'base_amount' => WithholdingCalculator::roundMoney((float) ($row['base_amount'] ?? 0)),
                'rate' => (float) ($row['rate'] ?? 0),
                'amount' => $amount,
                'account_code' => $row['account_code'] ?? null,
                'comment' => $row['comment'] ?? null,
            ];
        }

        return $rows;
    }

    private function withholdingGuard(string $kind, ?int $exceptIndex = null): ?string
    {
        if (! in_array($kind, [WithholdingKind::VAT, WithholdingKind::IS], true)) {
            return null;
        }

        $pending = [];
        foreach ($this->withholdings as $i => $row) {
            if ($exceptIndex !== null && $i === $exceptIndex) {
                continue;
            }
            $pending[] = $row;
        }

        $fiscal = InvoiceFiscalBreakdown::fromInvoice($this->invoice);
        $already = $kind === WithholdingKind::VAT
            ? InvoiceFiscalBreakdown::alreadyWithheldVat($this->invoice, $pending)
            : InvoiceFiscalBreakdown::alreadyWithheldIs($this->invoice, $pending);

        return $fiscal->withholdingError($kind, $already);
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

    private function storeCertificateFile(InvoicePayment $payment, $file): void
    {
        app(InvoicePaymentsService::class)->storeUploadedCertificate($payment, $file);
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
