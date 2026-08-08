<?php

namespace InovCom\Payroll\Support;

/**
 * Convertit un montant entier en lettres (français), sans dépendre de l’extension intl.
 */
class AmountInWords
{
    private const UNITS = [
        0 => 'zéro',
        1 => 'un',
        2 => 'deux',
        3 => 'trois',
        4 => 'quatre',
        5 => 'cinq',
        6 => 'six',
        7 => 'sept',
        8 => 'huit',
        9 => 'neuf',
        10 => 'dix',
        11 => 'onze',
        12 => 'douze',
        13 => 'treize',
        14 => 'quatorze',
        15 => 'quinze',
        16 => 'seize',
        17 => 'dix-sept',
        18 => 'dix-huit',
        19 => 'dix-neuf',
    ];

    private const TENS = [
        2 => 'vingt',
        3 => 'trente',
        4 => 'quarante',
        5 => 'cinquante',
        6 => 'soixante',
        7 => 'soixante',
        8 => 'quatre-vingt',
        9 => 'quatre-vingt',
    ];

    public static function currencyLabel(?string $currency): string
    {
        $code = strtoupper(trim((string) $currency));

        return match ($code) {
            'XOF', 'XAF', 'CFA', '' => 'FCFA',
            default => $code !== '' ? $code : 'FCFA',
        };
    }

    public static function format(float|int $amount, ?string $currency = 'FCFA'): string
    {
        $whole = (int) round(abs((float) $amount));
        $label = self::currencyLabel($currency);
        $words = self::convert($whole);

        if ($amount < 0) {
            $words = 'moins '.$words;
        }

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1).' '.$label;
    }

    public static function convert(int $number): string
    {
        if ($number < 0) {
            return 'moins '.self::convert(abs($number));
        }

        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            return self::belowHundred($number);
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $rest = $number % 100;
            $prefix = $hundreds === 1 ? 'cent' : self::UNITS[$hundreds].' cent';
            if ($rest === 0) {
                return $hundreds > 1 ? $prefix.'s' : $prefix;
            }

            return $prefix.' '.self::belowHundred($rest);
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $rest = $number % 1000;
            $prefix = $thousands === 1
                ? 'mille'
                : self::convert($thousands).' mille';

            return $rest === 0 ? $prefix : $prefix.' '.self::convert($rest);
        }

        if ($number < 1000000000) {
            $millions = intdiv($number, 1000000);
            $rest = $number % 1000000;
            $prefix = $millions === 1
                ? 'un million'
                : self::convert($millions).' millions';

            return $rest === 0 ? $prefix : $prefix.' '.self::convert($rest);
        }

        $billions = intdiv($number, 1000000000);
        $rest = $number % 1000000000;
        $prefix = $billions === 1
            ? 'un milliard'
            : self::convert($billions).' milliards';

        return $rest === 0 ? $prefix : $prefix.' '.self::convert($rest);
    }

    private static function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        $tens = intdiv($number, 10);
        $unit = $number % 10;

        if ($tens === 7 || $tens === 9) {
            $base = self::TENS[$tens];
            $remainder = 10 + $unit;
            if ($tens === 7 && $unit === 1) {
                return 'soixante et onze';
            }

            return $base.'-'.self::UNITS[$remainder];
        }

        if ($tens === 8) {
            if ($unit === 0) {
                return 'quatre-vingts';
            }

            return 'quatre-vingt-'.self::UNITS[$unit];
        }

        $base = self::TENS[$tens];
        if ($unit === 0) {
            return $base;
        }
        if ($unit === 1) {
            return $base.' et un';
        }

        return $base.'-'.self::UNITS[$unit];
    }
}
