<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Models\InvoicePaymentAttachment;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
use InovCom\InvoicePayments\Support\PaymentSettlementLines;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoicePaymentShow extends Component
{
    use WithFileUploads;

    public InvoicePayment $invoicePayment;

    public $newCertificate = null;

    public function mount(InvoicePayment $invoicePayment): void
    {
        if (! $this->can('invoice_payments.view') && ! $this->can('invoice_payments.receive')) {
            abort(403);
        }

        $this->invoicePayment = $invoicePayment;
        $this->reloadPayment();
    }

    public function attachCertificate(): void
    {
        if (! $this->canAttach()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->validate([
            'newCertificate' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        try {
            app(InvoicePaymentsService::class)->storeUploadedCertificate(
                $this->invoicePayment,
                $this->newCertificate
            );
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->reset('newCertificate');
        $this->reloadPayment();
        session()->flash('success', 'Justificatif ajouté.');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if (! $this->canAttach()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $attachment = InvoicePaymentAttachment::query()
            ->where('invoice_payment_id', $this->invoicePayment->id)
            ->findOrFail($attachmentId);

        app(InvoicePaymentsService::class)->deleteAttachment($attachment);
        $this->reloadPayment();
        session()->flash('success', 'Justificatif retiré.');
    }

    public function render()
    {
        $this->reloadPayment();
        $payment = $this->invoicePayment;
        $invoice = $payment->invoice;
        $settlement = $invoice
            ? PaymentSettlementLines::fromModels($invoice, $payment)
            : PaymentSettlementLines::build(0, 0, [], (float) $payment->amount, $payment->settledAmount());

        return view('inovcom-invoice-payments::livewire.show')
            ->layout('layouts.app', [
                'title' => $payment->reference,
                'subtitle' => 'Détail de l\'encaissement',
            ])
            ->with([
                'payment' => $payment,
                'invoice' => $invoice,
                'settlement' => $settlement,
                'canReceive' => $invoice && $invoice->canReceivePayment() && $this->can('invoice_payments.receive'),
                'canAttach' => $this->canAttach(),
            ]);
    }

    private function reloadPayment(): void
    {
        $this->invoicePayment->refresh();
        $this->invoicePayment->load(array_merge(
            ['invoice.client', 'invoice.taxLines', 'invoice.quotation', 'creator', 'canceller'],
            InvoicePayment::optionalWithholdingsRelation(),
            InvoicePayment::optionalAttachmentsRelation(),
        ));
    }

    private function canAttach(): bool
    {
        return $this->can('invoice_payments.receive') && ! $this->invoicePayment->isCancelled();
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
