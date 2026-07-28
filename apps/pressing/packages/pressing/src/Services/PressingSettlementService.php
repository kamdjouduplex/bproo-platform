<?php

namespace Pressing\Services;

use Illuminate\Support\Facades\Auth;
use Pressing\Models\PressingOrder;

class PressingSettlementService
{
    public const CREDIT_PENDING = 'pending';

    public const CREDIT_APPROVED = 'approved';

    public const CREDIT_REJECTED = 'rejected';

    public function remainingBalance(PressingOrder $order): float
    {
        return max(0, round((float) $order->balance, 2));
    }

    public function isFullyPaid(PressingOrder $order): bool
    {
        return $this->remainingBalance($order) <= 0;
    }

    public function hasApprovedCredit(PressingOrder $order): bool
    {
        if ($order->credit_status !== self::CREDIT_APPROVED) {
            return false;
        }

        return (float) $order->credit_amount >= $this->remainingBalance($order) - 0.009
            || $this->remainingBalance($order) <= 0;
    }

    /** Delivery allowed only if paid or approved credit covers unpaid balance. */
    public function canDeliver(PressingOrder $order): bool
    {
        if ($this->isFullyPaid($order)) {
            return true;
        }

        return $order->credit_status === self::CREDIT_APPROVED
            && (float) $order->credit_amount >= $this->remainingBalance($order) - 0.009;
    }

    public function deliveryBlockReason(PressingOrder $order): ?string
    {
        if ($this->canDeliver($order)) {
            return null;
        }

        $balance = number_format($this->remainingBalance($order), 0, ',', ' ');

        if ($order->credit_status === self::CREDIT_PENDING) {
            return __('Solde :balance FCFA — crédit en attente de validation. Un profil habilité doit valider le crédit avant la remise.', [
                'balance' => $balance,
            ]);
        }

        if ($order->credit_status === self::CREDIT_REJECTED) {
            return __('Solde :balance FCFA — crédit refusé. Encaissez le reste ou faites une nouvelle demande de crédit.', [
                'balance' => $balance,
            ]);
        }

        return __('Solde :balance FCFA non réglé. Encaissez la facture ou demandez un crédit client avant de marquer comme livré.', [
            'balance' => $balance,
        ]);
    }

    public function requestCredit(PressingOrder $order, ?string $notes = null): PressingOrder
    {
        $balance = $this->remainingBalance($order);
        if ($balance <= 0) {
            throw new \InvalidArgumentException(__('La commande est déjà soldée — aucun crédit nécessaire.'));
        }

        if ($order->credit_status === self::CREDIT_PENDING) {
            throw new \InvalidArgumentException(__('Un crédit est déjà en attente de validation pour cette commande.'));
        }

        if ($order->credit_status === self::CREDIT_APPROVED && (float) $order->credit_amount >= $balance) {
            throw new \InvalidArgumentException(__('Un crédit validé couvre déjà ce solde.'));
        }

        $order->update([
            'credit_status' => self::CREDIT_PENDING,
            'credit_amount' => $balance,
            'credit_notes' => $notes ? trim($notes) : null,
            'credit_requested_by' => Auth::guard('tenant')->id(),
            'credit_requested_at' => now(),
            'credit_validated_by' => null,
            'credit_validated_at' => null,
            'credit_rejection_reason' => null,
        ]);

        return $order->fresh();
    }

    public function approveCredit(PressingOrder $order): PressingOrder
    {
        if ($order->credit_status !== self::CREDIT_PENDING) {
            throw new \InvalidArgumentException(__('Aucun crédit en attente à valider.'));
        }

        $order->update([
            'credit_status' => self::CREDIT_APPROVED,
            'credit_amount' => $this->remainingBalance($order) > 0
                ? $this->remainingBalance($order)
                : (float) $order->credit_amount,
            'credit_validated_by' => Auth::guard('tenant')->id(),
            'credit_validated_at' => now(),
            'credit_rejection_reason' => null,
        ]);

        return $order->fresh();
    }

    public function rejectCredit(PressingOrder $order, ?string $reason = null): PressingOrder
    {
        if ($order->credit_status !== self::CREDIT_PENDING) {
            throw new \InvalidArgumentException(__('Aucun crédit en attente à refuser.'));
        }

        $order->update([
            'credit_status' => self::CREDIT_REJECTED,
            'credit_validated_by' => Auth::guard('tenant')->id(),
            'credit_validated_at' => now(),
            'credit_rejection_reason' => $reason ? trim($reason) : null,
        ]);

        return $order->fresh();
    }

    public function creditStatusLabel(?string $status): string
    {
        return match ($status) {
            self::CREDIT_PENDING => 'Crédit en attente',
            self::CREDIT_APPROVED => 'Crédit validé',
            self::CREDIT_REJECTED => 'Crédit refusé',
            default => 'Aucun crédit',
        };
    }
}
