<?php

namespace InovCom\Purchases\Support;

class PurchaseVatCalculator
{
    public const MODE_HT = 'ht';
    public const MODE_TTC = 'ttc';

    /**
     * @return array{
     *     unit_price_ht: float,
     *     unit_price_ttc: float,
     *     unit_vat: float,
     *     vat_rate: float,
     *     vat_amount: float,
     *     unit_price: float,
     *     line_total_ht: float,
     *     line_total_ttc: float,
     *     line_total: float
     * }
     */
    public static function fromEntered(
        float $enteredUnitPrice,
        float $quantity,
        float $vatRatePercent,
        string $priceMode = self::MODE_HT,
        bool $hasVat = false,
        bool $vatDeductible = true
    ): array {
        $enteredUnitPrice = max(0, round($enteredUnitPrice, 2));
        $quantity = max(0, $quantity);
        $ratePercent = $hasVat ? max(0, $vatRatePercent) : 0.0;
        $rate = $ratePercent / 100;

        if ($rate <= 0) {
            $ht = $enteredUnitPrice;
            $ttc = $enteredUnitPrice;
            $unitVat = 0.0;
        } elseif ($priceMode === self::MODE_TTC) {
            $ttc = $enteredUnitPrice;
            $ht = round($ttc / (1 + $rate), 2);
            $unitVat = round($ttc - $ht, 2);
        } else {
            $ht = $enteredUnitPrice;
            $unitVat = round($ht * $rate, 2);
            $ttc = round($ht + $unitVat, 2);
        }

        $stockCost = $vatDeductible ? $ht : $ttc;

        return [
            'unit_price_ht' => $ht,
            'unit_price_ttc' => $ttc,
            'unit_vat' => $unitVat,
            'vat_rate' => round($ratePercent, 4),
            'vat_amount' => round($unitVat * $quantity, 2),
            'unit_price' => $stockCost,
            'line_total_ht' => round($ht * $quantity, 2),
            'line_total_ttc' => round($ttc * $quantity, 2),
            'line_total' => round($stockCost * $quantity, 2),
        ];
    }
}
