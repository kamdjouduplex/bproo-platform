<?php

namespace App\Support;

/**
 * Indicateurs de marge commerciaux (coût / marge) pour devis & factures.
 * Réservés à l’écran (jamais à l’impression client).
 */
class DocumentMargin
{
    /**
     * @param  list<array<string, mixed>>  $cart
     * @param  array<int, float|string|null>  $itemCostsById  item_id => cost
     * @return array{total_cost: float, margin: float, margin_percent: ?float, revenue_ht: float}
     */
    public static function fromCart(array $cart, float $revenueHt, array $itemCostsById = []): array
    {
        $totalCost = 0.0;

        foreach ($cart as $row) {
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty == 0.0) {
                continue;
            }

            $unitCost = self::unitCostForRow($row, $itemCostsById);
            $totalCost += $qty * $unitCost;
        }

        $totalCost = round($totalCost, 2);
        $revenueHt = round(max(0, $revenueHt), 2);
        $margin = round($revenueHt - $totalCost, 2);
        $marginPercent = $revenueHt > 0
            ? round($margin / $revenueHt * 100, 1)
            : null;

        return [
            'total_cost' => $totalCost,
            'margin' => $margin,
            'margin_percent' => $marginPercent,
            'revenue_ht' => $revenueHt,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, float|string|null>  $itemCostsById
     */
    public static function unitCostForRow(array $row, array $itemCostsById = []): float
    {
        if (array_key_exists('unit_cost', $row) && $row['unit_cost'] !== null && $row['unit_cost'] !== '') {
            return max(0, (float) $row['unit_cost']);
        }

        $itemId = (int) ($row['item_id'] ?? 0);
        if ($itemId > 0 && array_key_exists($itemId, $itemCostsById)) {
            return max(0, (float) $itemCostsById[$itemId]);
        }

        return 0.0;
    }
}
