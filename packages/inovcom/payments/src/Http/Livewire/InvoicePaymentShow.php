<?php

namespace InovCom\InvoicePayments\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Models\InvoicePaymentAttachment;
use InovCom\InvoicePayments\Services\InvoicePaymentsService;
use InovCom\InvoicePayments\Support\PaymentSettlementLines;
use InovCom\InvoicePayments\Support\WithholdingSchema;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoicePaymentShow extends Component
{
    use WithFileUploads;

    public InvoicePayment $invoicePayment;

    public $newCertificate = null;

    /** @var array<int|string, mixed> */
    public array $replaceCertificates = [];

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
        if (! $this->canAttach()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->validate([
            'replaceCertificates.'.$attachmentId => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        $attachment = InvoicePaymentAttachment::query()
            ->where('invoice_payment_id', $this->invoicePayment->id)
            ->findOrFail($attachmentId);

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
        $this->reloadPayment();
        session()->flash('success', 'Justificatif mis à jour.');
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
        $attachments = $this->freshAttachments();
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
                'attachments' => $attachments,
                'settlement' => $settlement,
                'canReceive' => $invoice && $invoice->canReceivePayment() && $this->can('invoice_payments.receive'),
                'canAttach' => $this->canAttach(),
            ]);
    }

    private function reloadPayment(): void
    {
        WithholdingSchema::ensure();
        $this->invoicePayment->refresh();
        $this->invoicePayment->unsetRelation('attachments');
        $this->invoicePayment->load(array_merge(
            ['invoice.client', 'invoice.taxLines', 'invoice.quotation', 'creator', 'canceller'],
            InvoicePayment::optionalWithholdingsRelation(),
            InvoicePayment::optionalAttachmentsRelation(),
        ));
    }

    private function freshAttachments()
    {
        if (! InvoicePayment::hasAttachmentsTable()) {
            return collect();
        }

        return InvoicePaymentAttachment::query()
            ->where('invoice_payment_id', $this->invoicePayment->id)
            ->orderByDesc('id')
            ->get();
    }

    private function canAttach(): bool
    {
        return $this->can('invoice_payments.receive')
            && ! $this->invoicePayment->isCancelled()
            && $this->invoicePayment->hasSourceWithholding();
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
