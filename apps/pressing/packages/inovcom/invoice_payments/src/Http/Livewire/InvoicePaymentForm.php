<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
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

    public ?int $cancellingPaymentId = null;
    public string $cancellation_reason = '';

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['client', 'lines']);

        if (!$this->can('invoice_payments.receive')) {
            abort(403);
        }

        $this->payment_date = now()->format('Y-m-d');
        $this->resetAmountToBalance();
    }

    public function resetAmountToBalance(): void
    {
        $this->invoice->refresh();
        $this->amount = $this->invoice->canReceivePayment()
            ? (string) round((float) $this->invoice->balance, 2)
            : '';
    }

    public function payFullBalance(): void
    {
        $this->resetAmountToBalance();
    }

    public function save(): void
    {
        if (!$this->can('invoice_payments.receive')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->invoice->refresh();
        $maxBalance = max(0, (float) $this->invoice->balance);

        $data = $this->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, $maxBalance + 0.01),
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,bank_transfer,mobile_money,other',
            'notes' => 'nullable|string|max:500',
            'external_reference' => 'nullable|string|max:100',
        ], [
            'amount.max' => 'Le montant ne peut pas dépasser le solde restant (' . fmt_money($maxBalance) . ' FCFA).',
            'amount.min' => 'Le montant doit être strictement positif.',
        ]);

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
                $data['external_reference'] ?? null
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

        $payments = InvoicePayment::query()
            ->where('invoice_id', $this->invoice->id)
            ->with(['creator', 'canceller'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $canReceive = $this->invoice->canReceivePayment() && $this->can('invoice_payments.receive');

        return view('inovcom-invoice-payments::livewire.payment-form')
            ->layout('layouts.app', [
                'title' => 'Encaissement',
                'subtitle' => $this->invoice->invoice_number,
            ])
            ->with([
                'payments' => $payments,
                'canReceive' => $canReceive,
                'canCancel' => $this->can('invoice_payments.cancel'),
            ]);
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
