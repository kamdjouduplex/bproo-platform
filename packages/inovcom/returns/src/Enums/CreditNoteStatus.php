<?php

namespace InovCom\Returns\Enums;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case PartiallyUsed = 'partially_used';
    case Used = 'used';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validé',
            self::PartiallyUsed => 'Partiellement utilisé',
            self::Used => 'Utilisé',
            self::Cancelled => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-secondary',
            self::Validated => 'badge-info',
            self::PartiallyUsed => 'badge-warning',
            self::Used => 'badge-success',
            self::Cancelled => 'badge-error',
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::Validated, self::PartiallyUsed], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
