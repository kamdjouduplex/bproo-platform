<?php

namespace InovCom\Invoicing\Support;

/**
 * Converts integer amounts to French words (Franc CFA).
 */
class AmountInWords
{
    private const UNITS = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt',
    ];

    public static function french(int $amount, string $currencyLabel = 'Franc(s) CFA'): string
    {
        if ($amount === 0) {
            return 'zéro ' . $currencyLabel;
        }

        if ($amount < 0) {
            return 'moins ' . self::french(abs($amount), $currencyLabel);
        }

        $parts = [];

        if ($amount >= 1_000_000) {
            $millions = intdiv($amount, 1_000_000);
            $amount %= 1_000_000;
            $parts[] = self::chunk($millions) . ' ' . ($millions > 1 ? 'millions' : 'million');
        }

        if ($amount >= 1_000) {
            $thousands = intdiv($amount, 1_000);
            $amount %= 1_000;
            if ($thousands > 1) {
                $parts[] = self::chunk($thousands) . ' mille';
            } else {
                $parts[] = 'mille';
            }
        }

        if ($amount > 0) {
            $parts[] = self::chunk($amount);
        }

        $words = trim(implode(' ', array_filter($parts)));

        return $words . ' ' . $currencyLabel;
    }

    private static function chunk(int $n): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        if ($n < 100) {
            $ten = intdiv($n, 10);
            $unit = $n % 10;

            if ($ten === 7 || $ten === 9) {
                $base = $ten === 7 ? 'soixante' : 'quatre-vingt';
                $rest = $ten === 7 ? 10 + $unit : 10 + $unit;

                return $base . ($rest === 11 ? '-et-onze' : '-' . self::UNITS[$rest]);
            }

            if ($unit === 0) {
                return self::TENS[$ten] . ($ten === 8 ? 's' : '');
            }

            if ($unit === 1 && $ten !== 8) {
                return self::TENS[$ten] . '-et-un';
            }

            return self::TENS[$ten] . '-' . self::UNITS[$unit];
        }

        $hundred = intdiv($n, 100);
        $rest = $n % 100;
        $hundredWord = $hundred > 1 ? self::UNITS[$hundred] . ' cent' : 'cent';

        if ($rest === 0) {
            return $hundredWord . ($hundred > 1 ? 's' : '');
        }

        return $hundredWord . ' ' . self::chunk($rest);
    }
}
