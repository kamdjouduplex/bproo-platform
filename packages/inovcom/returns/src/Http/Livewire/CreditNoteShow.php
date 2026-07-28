<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Enums\RefundMethod;
use InovCom\Returns\Models\CreditNote;
use InovCom\Returns\Services\CreditNoteService;
use InovCom\Returns\Services\CustomerCreditService;
use InovCom\Returns\Services\RefundService;
use Livewire\Component;

class CreditNoteShow extends Component
{
    use AuthorizesReturnActions;

    public int $creditNoteId;

    public string $applyAmount = '';
    public string $refundMethod = 'cash';
    public string $refundAmount = '';
    public string $refundReference = '';
    public string $creditAmount = '';

    public function mount(int $creditNote): void
    {
        $this->creditNoteId = $creditNote;
    }

    private function model(): CreditNote
    {
        return CreditNote::on('tenant')
            ->with(['items', 'client', 'returnRequest', 'refunds'])
            ->findOrFail($this->creditNoteId);
    }

    public function applyToInvoice(): void
    {
        if (! $this->can('credit_notes.use')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            $amount = $this->applyAmount !== '' ? (float) $this->applyAmount : null;
            $applied = app(CreditNoteService::class)->applyToInvoice($this->model(), null, $amount, $this->tenantUserId());
            session()->flash('success', 'Avoir imputé sur la facture : ' . fmt_money($applied) . ' FCFA.');
            $this->applyAmount = '';
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function issueRefund(): void
    {
        if (! $this->can('refunds.create')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            $method = RefundMethod::from($this->refundMethod);
            $amount = $this->refundAmount !== '' ? (float) $this->refundAmount : null;
            $refund = app(RefundService::class)->issueFromCreditNote(
                $this->model(),
                $method,
                $amount,
                $this->refundReference ?: null,
                null,
                $this->tenantUserId(),
            );
            session()->flash('success', 'Remboursement ' . $refund->refund_number . ' enregistré.');
            $this->reset(['refundAmount', 'refundReference']);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function convertToCredit(): void
    {
        if (! $this->can('customer_credits.use')) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            $amount = $this->creditAmount !== '' ? (float) $this->creditAmount : null;
            $value = app(CreditNoteService::class)->convertToCustomerCredit($this->model(), $amount, $this->tenantUserId());
            session()->flash('success', 'Crédit client alimenté : ' . fmt_money($value) . ' FCFA.');
            $this->creditAmount = '';
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $creditNote = $this->model();
        $walletBalance = app(CustomerCreditService::class)->balance((int) $creditNote->client_id);

        return view('inovcom-returns::livewire.credit-notes.show')
            ->layout('layouts.app', [
                'title' => 'Avoir ' . $creditNote->credit_note_number,
                'subtitle' => $creditNote->client?->name,
            ])
            ->with([
                'creditNote' => $creditNote,
                'walletBalance' => $walletBalance,
                'methods' => RefundMethod::options(),
                'canUse' => $this->can('credit_notes.use'),
                'canRefund' => $this->can('refunds.create'),
                'canCredit' => $this->can('customer_credits.use'),
            ]);
    }
}
