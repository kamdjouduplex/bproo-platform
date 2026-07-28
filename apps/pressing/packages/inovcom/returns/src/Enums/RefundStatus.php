<?php

namespace InovCom\Returns\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Validated => 'Validé',
            self::Paid => 'Payé',
            self::Cancelled => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-warning',
            self::Validated => 'badge-info',
            self::Paid => 'badge-success',
            self::Cancelled => 'badge-error',
        };
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
