<?php

namespace InovCom\Facturation\Support;

class PaymentMethodLabels
{
    public static function options(): array
    {
        return [
            'virement'      => __('Virement bancaire'),
            'cheque'        => __('Chèque'),
            'especes'       => __('Espèces'),
            'mobile_money'  => __('Mobile Money'),
            'carte'         => __('Carte bancaire'),
            'autre'         => __('Autre'),
        ];
    }

    public static function label(?string $method): string
    {
        if (!$method) {
            return '—';
        }

        return self::options()[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }
}
