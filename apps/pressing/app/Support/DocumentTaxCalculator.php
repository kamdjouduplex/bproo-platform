<?php

namespace App\Support;

class DocumentTaxCalculator
{
    public const EFFECT_ADD = 'add';
    public const EFFECT_SUBTRACT = 'subtract';

    /**
     * @param  array<int, array{name?:string, mode?:string, rate?:mixed, amount?:mixed, effect?:string}>  $inputLines
     * @return array{
     *     lines: array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float,tax_effect:string}>,
     *     additive: float,
     *     subtractive: float,
     *     tax_amount: float,
     *     ttc: float,
     *     total: float
     * }
     */
    public static function summarize(float $netHt, array $inputLines): array
    {
        $normalized = [];
        $additive = 0.0;
        $subtractive = 0.0;
        $fallbackTaxRate = 0.0;

        foreach ($inputLines as $line) {
            $name = trim((string) ($line['name'] ?? $line['tax_name'] ?? ''));
            if ($name === '') {
                $name = 'Taxe';
            }

            $mode = (string) ($line['mode'] ?? $line['tax_mode'] ?? 'amount');
            $mode = in_array($mode, ['percent', 'amount'], true) ? $mode : 'amount';

            $effect = (string) ($line['effect'] ?? $line['tax_effect'] ?? self::EFFECT_ADD);
            $effect = $effect === self::EFFECT_SUBTRACT ? self::EFFECT_SUBTRACT : self::EFFECT_ADD;

            if ($mode === 'percent') {
                $rate = max(0, (float) ($line['rate'] ?? $line['tax_rate'] ?? 0));
                $amount = round($netHt * ($rate / 100), 2);
            } else {
                $amount = round(max(0, (float) ($line['amount'] ?? $line['tax_amount'] ?? 0)), 2);
                $rate = null;
            }

            if ($amount <= 0) {
                continue;
            }

            $normalized[] = [
                'tax_name' => $name,
                'tax_mode' => $mode,
                'tax_rate' => $mode === 'percent' ? (float) ($rate ?? 0) : null,
                'tax_amount' => $amount,
                'tax_effect' => $effect,
            ];

            if ($effect === self::EFFECT_SUBTRACT) {
                $subtractive += $amount;
            } else {
                $additive += $amount;
                if ($mode === 'percent') {
                    $fallbackTaxRate += (float) ($rate ?? 0);
                }
            }
        }

        $ttc = round($netHt + $additive, 2);
        $total = $subtractive > 0
            ? round($netHt - $subtractive, 2)
            : $ttc;

        return [
            'lines' => $normalized,
            'additive' => round($additive, 2),
            'subtractive' => round($subtractive, 2),
            'tax_amount' => round($additive - $subtractive, 2),
            'tax_rate' => round($fallbackTaxRate, 3),
            'ttc' => $ttc,
            'total' => max(0, $total),
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $taxLines
     * @return array{
     *     lines: array<int, array{tax_name:string,tax_mode:string,tax_rate:?float,tax_amount:float,tax_effect:string}>,
     *     additive: float,
     *     subtractive: float,
     *     tax_amount: float,
     *     tax_rate: float,
     *     ttc: float,
     *     total: float
     * }
     */
    public static function summarizeFromStoredTaxLines(float $netHt, iterable $taxLines, ?float $legacyTaxAmount = null): array
    {
        $input = [];
        foreach ($taxLines as $line) {
            $isArray = is_array($line);
            $input[] = [
                'name' => (string) ($isArray ? ($line['tax_name'] ?? '') : ($line->tax_name ?? '')),
                'mode' => (string) ($isArray ? ($line['tax_mode'] ?? 'amount') : ($line->tax_mode ?? 'amount')),
                'rate' => $isArray ? ($line['tax_rate'] ?? null) : ($line->tax_rate ?? null),
                'amount' => (float) ($isArray ? ($line['tax_amount'] ?? 0) : ($line->tax_amount ?? 0)),
                'effect' => (string) ($isArray ? ($line['tax_effect'] ?? self::EFFECT_ADD) : ($line->tax_effect ?? self::EFFECT_ADD)),
            ];
        }

        if ($input === [] && $legacyTaxAmount !== null && abs($legacyTaxAmount) > 0) {
            $input[] = [
                'name' => 'TVA',
                'mode' => 'amount',
                'amount' => abs($legacyTaxAmount),
                'effect' => self::EFFECT_ADD,
            ];
        }

        return self::summarize($netHt, $input);
    }

    public static function normalizeEffect(?string $effect): string
    {
        return $effect === self::EFFECT_SUBTRACT ? self::EFFECT_SUBTRACT : self::EFFECT_ADD;
    }
}
