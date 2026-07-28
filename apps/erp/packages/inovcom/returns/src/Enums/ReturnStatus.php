<?php

namespace InovCom\Returns\Enums;

enum ReturnStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Inspected = 'inspected';
    case CreditNoteCreated = 'credit_note_created';
    case Refunded = 'refunded';
    case CreditApplied = 'credit_applied';
    case Replaced = 'replaced';
    case Exchanged = 'exchanged';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Requested => 'Demandé',
            self::PendingApproval => 'En attente de validation',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
            self::Received => 'Reçu',
            self::Inspected => 'Contrôlé',
            self::CreditNoteCreated => 'Avoir créé',
            self::Refunded => 'Remboursé',
            self::CreditApplied => 'Crédit client',
            self::Replaced => 'Remplacé',
            self::Exchanged => 'Échangé',
            self::Closed => 'Clôturé',
            self::Cancelled => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-secondary',
            self::Requested, self::PendingApproval => 'badge-warning',
            self::Approved, self::Received, self::Inspected => 'badge-info',
            self::CreditNoteCreated, self::Refunded, self::CreditApplied,
            self::Replaced, self::Exchanged, self::Closed => 'badge-success',
            self::Rejected, self::Cancelled => 'badge-error',
        };
    }

    /**
     * Statuts vers lesquels une transition est autorisée.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Requested, self::Cancelled],
            self::Requested => [self::PendingApproval, self::Approved, self::Rejected, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Received, self::Cancelled],
            self::Received => [self::Inspected, self::Cancelled],
            self::Inspected => [self::CreditNoteCreated, self::Replaced, self::Exchanged],
            self::CreditNoteCreated => [self::Refunded, self::CreditApplied, self::Closed],
            self::Refunded, self::CreditApplied, self::Replaced, self::Exchanged => [self::Closed],
            self::Rejected, self::Cancelled, self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled, self::Rejected], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft], true);
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
