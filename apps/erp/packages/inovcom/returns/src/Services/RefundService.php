<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Returns\Enums\RefundMethod;
use InovCom\Returns\Enums\RefundStatus;
use InovCom\Returns\Enums\ResolutionType;
use InovCom\Returns\Enums\ReturnStatus;
use InovCom\Returns\Models\CreditNote;
use InovCom\Returns\Models\Refund;

class RefundService
{
    public function __construct(
        private ReturnNumberGenerator $numbers,
        private ReturnService $returns,
        private CreditNoteService $creditNotes,
        private CustomerCreditService $wallet,
        private AuditLogger $audit,
    ) {
    }

    public function isReady(): bool
    {
        return Schema::connection('tenant')->hasTable('refunds');
    }

    /**
     * Émet un remboursement adossé à un avoir.
     * - Espèces : sortie de caisse (session ouverte requise).
     * - Crédit client : alimente le wallet.
     * - Virement / Mobile Money / Chèque : enregistrement avec référence externe.
     */
    public function issueFromCreditNote(
        CreditNote $creditNote,
        RefundMethod $method,
        ?float $amount = null,
        ?string $externalReference = null,
        ?string $notes = null,
        ?int $userId = null
    ): Refund {
        if (! $creditNote->isUsable()) {
            throw new \RuntimeException('Cet avoir n\'est plus utilisable.');
        }

        return DB::connection('tenant')->transaction(function () use (
            $creditNote, $method, $amount, $externalReference, $notes, $userId
        ) {
            $creditNote = CreditNote::on('tenant')->lockForUpdate()->findOrFail($creditNote->id);

            $value = round(min((float) $creditNote->remaining_amount, $amount ?? (float) $creditNote->remaining_amount), 2);
            if ($value <= 0) {
                throw new \RuntimeException('Montant de remboursement invalide.');
            }

            // Crédit client : on délègue au wallet plutôt qu'à un décaissement.
            if ($method === RefundMethod::CustomerCredit) {
                $this->creditNotes->convertToCustomerCredit($creditNote, $value, $userId);

                return $this->record($creditNote, $method, $value, RefundStatus::Paid, $externalReference, $notes, null, $userId);
            }

            $caisseEntryId = null;
            if ($method === RefundMethod::Cash) {
                $caisseEntryId = $this->cashOut($creditNote, $value, $userId);
            }

            $status = $method === RefundMethod::Cash ? RefundStatus::Paid : RefundStatus::Validated;
            $refund = $this->record($creditNote, $method, $value, $status, $externalReference, $notes, $caisseEntryId, $userId);

            $this->creditNotes->consume($creditNote, $value);
            $this->settleReturn($creditNote, $userId);

            $this->audit->log('refund', $refund->id, 'issued', [
                'method' => $method->value,
                'amount' => $value,
            ], $userId);

            return $refund;
        });
    }

    public function markPaid(Refund $refund, ?int $userId = null): Refund
    {
        if ($refund->status === RefundStatus::Paid) {
            return $refund;
        }

        $refund->status = RefundStatus::Paid->value;
        $refund->validated_by = $userId ?? auth('tenant')->id();
        $refund->validated_at = now();
        $refund->save();

        $this->audit->log('refund', $refund->id, 'paid', [], $userId);

        return $refund;
    }

    private function cashOut(CreditNote $creditNote, float $amount, ?int $userId): ?int
    {
        if (! class_exists(\InovCom\Caisse\Services\CaisseService::class)) {
            throw new \RuntimeException('Le module Caisse est requis pour un remboursement en espèces.');
        }

        $caisse = app(\InovCom\Caisse\Services\CaisseService::class);
        if (! $caisse->isReady()) {
            throw new \RuntimeException('La caisse n\'est pas initialisée.');
        }

        $entry = $caisse->cashOut(
            amount: $amount,
            reason: 'Remboursement avoir ' . $creditNote->credit_note_number,
            userId: $userId,
            referenceNumber: $creditNote->credit_note_number,
            source: 'avoir',
            type: 'avoir_refund_cash_out',
            enforceBalance: false,
        );

        return $entry?->id;
    }

    private function record(
        CreditNote $creditNote,
        RefundMethod $method,
        float $amount,
        RefundStatus $status,
        ?string $externalReference,
        ?string $notes,
        ?int $caisseEntryId,
        ?int $userId
    ): Refund {
        return Refund::create([
            'refund_number' => $this->numbers->nextRefundNumber(),
            'client_id' => $creditNote->client_id,
            'credit_note_id' => $creditNote->id,
            'return_id' => $creditNote->return_id,
            'status' => $status->value,
            'method' => $method->value,
            'amount' => $amount,
            'refund_date' => now()->toDateString(),
            'caisse_entry_id' => $caisseEntryId,
            'external_reference' => $externalReference,
            'notes' => $notes,
            'store_id' => $creditNote->store_id,
            'created_by' => $userId ?? auth('tenant')->id(),
            'validated_by' => $status === RefundStatus::Paid ? ($userId ?? auth('tenant')->id()) : null,
            'validated_at' => $status === RefundStatus::Paid ? now() : null,
        ]);
    }

    private function settleReturn(CreditNote $creditNote, ?int $userId): void
    {
        $return = $creditNote->returnRequest;
        if (! $return) {
            return;
        }

        if ($return->status === ReturnStatus::CreditNoteCreated) {
            $return->resolution_type = ResolutionType::Refund->value;
            $return->save();
            $this->returns->changeStatus($return, ReturnStatus::Refunded, 'Remboursement émis', $userId);
        }
    }
}
