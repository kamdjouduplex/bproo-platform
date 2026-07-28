<?php

namespace InovCom\Returns\Enums;

enum ResolutionType: string
{
    case CreditNote = 'credit_note';
    case Refund = 'refund';
    case CustomerCredit = 'customer_credit';
    case Replacement = 'replacement';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::CreditNote => 'Avoir (imputé facture)',
            self::Refund => 'Remboursement',
            self::CustomerCredit => 'Crédit client',
            self::Replacement => 'Remplacement',
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
