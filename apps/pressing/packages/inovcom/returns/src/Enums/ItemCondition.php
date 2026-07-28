<?php

namespace InovCom\Returns\Enums;

enum ItemCondition: string
{
    case Resellable = 'resellable';
    case Defective = 'defective';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Resellable => 'Revendable',
            self::Defective => 'Défectueux',
            self::Damaged => 'Endommagé',
        };
    }

    /**
     * Un produit revendable réintègre le stock vendable ;
     * les autres partent en perte / quarantaine.
     */
    public function isRestockable(): bool
    {
        return $this === self::Resellable;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Resellable => 'badge-success',
            self::Defective => 'badge-warning',
            self::Damaged => 'badge-error',
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
