<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Returns\Enums\CreditNoteStatus;
use InovCom\Returns\Enums\ResolutionType;
use InovCom\Returns\Enums\ReturnStatus;
use InovCom\Returns\Models\CreditNote;
use InovCom\Returns\Models\ReturnRequest;

class CreditNoteService
{
    public function __construct(
        private ReturnNumberGenerator $numbers,
        private ReturnService $returns,
        private CustomerCreditService $wallet,
        private AuditLogger $audit,
    ) {
    }

    public function isReady(): bool
    {
        return Schema::connection('tenant')->hasTable('credit_notes');
    }

    /**
     * Génère un avoir validé à partir d'un retour contrôlé (INSPECTED).
     */
    public function issueFromReturn(ReturnRequest $return, ?int $userId = null): CreditNote
    {
        if ($return->status !== ReturnStatus::Inspected) {
            throw new \RuntimeException('Le retour doit être contrôlé avant la création de l\'avoir.');
        }

        if ($return->creditNote()->exists()) {
            throw new \RuntimeException('Un avoir existe déjà pour ce retour.');
        }

        return DB::connection('tenant')->transaction(function () use ($return, $userId) {
            $return->loadMissing('items');

            $subtotal = round((float) $return->subtotal_amount, 2);
            $tax = round((float) $return->tax_amount, 2);
            $total = round((float) $return->total_amount, 2);

            $creditNote = CreditNote::create([
                'credit_note_number' => $this->numbers->nextCreditNoteNumber(),
                'client_id' => $return->client_id,
                'return_id' => $return->id,
                'invoice_id' => $return->source_type === 'invoice' ? $return->source_id : null,
                'status' => CreditNoteStatus::Validated->value,
                'issue_date' => now()->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $total,
                'used_amount' => 0,
                'remaining_amount' => $total,
                'reason' => $return->notes,
                'store_id' => $return->store_id,
                'created_by' => $userId ?? auth('tenant')->id(),
                'validated_by' => $userId ?? auth('tenant')->id(),
                'validated_at' => now(),
            ]);

            foreach ($return->items as $item) {
                $creditNote->items()->create([
                    'return_item_id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'line_total' => $item->line_total,
                ]);
            }

            $return->resolution_type = ResolutionType::CreditNote->value;
            $return->save();
            $this->returns->changeStatus($return, ReturnStatus::CreditNoteCreated, 'Avoir ' . $creditNote->credit_note_number . ' généré', $userId);

            $this->audit->log('credit_note', $creditNote->id, 'created', ['total' => $total], $userId);

            return $creditNote->fresh(['items']);
        });
    }

    /**
     * Impute l'avoir sur le solde d'une facture (réduit la créance).
     * Crée un encaissement positif (méthode « autre ») limité au solde dû.
     */
    public function applyToInvoice(CreditNote $creditNote, ?int $invoiceId = null, ?float $amount = null, ?int $userId = null): float
    {
        if (! $creditNote->isUsable()) {
            throw new \RuntimeException('Cet avoir n\'est plus utilisable.');
        }

        if (! Schema::connection('tenant')->hasTable('invoice_payments')) {
            throw new \RuntimeException('Le module Paiements factures est requis pour imputer un avoir.');
        }

        $invoiceId = $invoiceId ?? $creditNote->invoice_id;
        if (! $invoiceId) {
            throw new \RuntimeException('Aucune facture cible pour cet avoir.');
        }

        return DB::connection('tenant')->transaction(function () use ($creditNote, $invoiceId, $amount, $userId) {
            $invoice = Invoice::on('tenant')->lockForUpdate()->findOrFail($invoiceId);
            $creditNote = CreditNote::on('tenant')->lockForUpdate()->findOrFail($creditNote->id);

            $balance = round((float) $invoice->balance, 2);
            if ($balance <= 0.01) {
                throw new \RuntimeException('Cette facture est déjà soldée : utilisez un remboursement ou un crédit client.');
            }

            $applicable = round(min((float) $creditNote->remaining_amount, $balance, $amount ?? $balance), 2);
            if ($applicable <= 0) {
                throw new \RuntimeException('Montant à imputer invalide.');
            }

            $paidBefore = round((float) $invoice->amount_paid, 2);
            \InovCom\InvoicePayments\Models\InvoicePayment::create([
                'reference' => $creditNote->credit_note_number . '/' . $invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'amount' => $applicable,
                'status' => \InovCom\InvoicePayments\Models\InvoicePayment::STATUS_ACTIVE,
                'amount_paid_before' => $paidBefore,
                'balance_after' => round((float) $invoice->total - $paidBefore - $applicable, 2),
                'payment_date' => now()->toDateString(),
                'payment_method' => 'other',
                'external_reference' => $creditNote->credit_note_number,
                'notes' => 'Imputation avoir ' . $creditNote->credit_note_number,
                'created_by' => $userId ?? auth('tenant')->id(),
            ]);

            app(\InovCom\InvoicePayments\Services\InvoicePaymentsService::class)
                ->syncInvoiceFromPayments($invoice);

            $this->consume($creditNote, $applicable);
            $this->settleReturn($creditNote, ReturnStatus::CreditApplied, $userId);

            $this->audit->log('credit_note', $creditNote->id, 'applied_to_invoice', [
                'invoice_id' => $invoice->id,
                'amount' => $applicable,
            ], $userId);

            return $applicable;
        });
    }

    /**
     * Convertit le reliquat de l'avoir en crédit client (wallet).
     */
    public function convertToCustomerCredit(CreditNote $creditNote, ?float $amount = null, ?int $userId = null): float
    {
        if (! $creditNote->isUsable()) {
            throw new \RuntimeException('Cet avoir n\'est plus utilisable.');
        }

        return DB::connection('tenant')->transaction(function () use ($creditNote, $amount, $userId) {
            $creditNote = CreditNote::on('tenant')->lockForUpdate()->findOrFail($creditNote->id);
            $value = round(min((float) $creditNote->remaining_amount, $amount ?? (float) $creditNote->remaining_amount), 2);
            if ($value <= 0) {
                throw new \RuntimeException('Montant à convertir invalide.');
            }

            $this->wallet->grant(
                clientId: (int) $creditNote->client_id,
                amount: $value,
                sourceType: 'credit_note',
                sourceId: $creditNote->id,
                reference: $creditNote->credit_note_number,
                notes: 'Avoir converti en crédit client',
                userId: $userId,
            );

            $this->consume($creditNote, $value);
            $this->settleReturn($creditNote, ReturnStatus::CreditApplied, $userId);

            $this->audit->log('credit_note', $creditNote->id, 'converted_to_customer_credit', ['amount' => $value], $userId);

            return $value;
        });
    }

    /**
     * Décrémente le reliquat d'un avoir et recalcule son statut.
     */
    public function consume(CreditNote $creditNote, float $amount): void
    {
        $amount = round($amount, 2);
        $creditNote->used_amount = round((float) $creditNote->used_amount + $amount, 2);
        $creditNote->remaining_amount = round((float) $creditNote->total - (float) $creditNote->used_amount, 2);

        if ($creditNote->remaining_amount <= 0.01) {
            $creditNote->remaining_amount = 0;
            $creditNote->status = CreditNoteStatus::Used->value;
        } else {
            $creditNote->status = CreditNoteStatus::PartiallyUsed->value;
        }

        $creditNote->save();
    }

    private function settleReturn(CreditNote $creditNote, ReturnStatus $target, ?int $userId): void
    {
        $return = $creditNote->returnRequest;
        if (! $return) {
            return;
        }

        if ($return->status === ReturnStatus::CreditNoteCreated) {
            $this->returns->changeStatus($return, $target, 'Résolution de l\'avoir', $userId);
        }
    }
}
