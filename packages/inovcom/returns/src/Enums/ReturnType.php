<?php

namespace InovCom\Returns\Enums;

enum ReturnType: string
{
    case Partial = 'partial';
    case Total = 'total';
    case Defective = 'defective';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::Partial => 'Retour partiel',
            self::Total => 'Retour total',
            self::Defective => 'Produit défectueux',
            self::Exchange => 'Échange',
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
