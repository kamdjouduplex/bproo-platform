<?php

namespace InovCom\Returns\Enums;

enum RefundMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Check = 'check';
    case CustomerCredit = 'customer_credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces (caisse)',
            self::BankTransfer => 'Virement bancaire',
            self::MobileMoney => 'Mobile Money',
            self::Check => 'Chèque',
            self::CustomerCredit => 'Crédit client (wallet)',
        };
    }

    public function usesCaisse(): bool
    {
        return $this === self::Cash;
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
