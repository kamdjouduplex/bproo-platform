<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Fiscal arithmetic for the admin dashboard.
 *
 * Amounts are rounded to whole francs (XAF/XOF have no cents).
 * HT = net of line discounts. TTC facturé = HT + TVA additive (not invoice.total,
 * which may already be net of invoice-level withholdings).
 */
class DashboardMetrics
{
    public const URGENCY_URGENT = 'urgent';
    public const URGENCY_WATCH = 'watch';
    public const URGENCY_NORMAL = 'normal';

    public static function round(float $value): float
    {
        return round($value, 0, PHP_ROUND_HALF_UP);
    }

    public static function netHt(float $subtotal, float $discount = 0.0): float
    {
        return self::round(max(0, $subtotal - $discount));
    }

    public static function billedTtc(float $ht, float $vatCollected): float
    {
        return self::round($ht + max(0, $vatCollected));
    }

    /**
     * Convert a TTC / net-à-payer amount into HT using the invoice ratio.
     */
    public static function allocateHt(float $amount, float $ht, float $total): float
    {
        if ($total <= 0 || $amount == 0.0) {
            return 0.0;
        }

        return self::round($amount * $ht / $total);
    }

    /**
     * VAT share of a cash payment: cash × TVA / TTC (HT + TVA).
     */
    public static function allocateVat(float $cash, float $vat, float $ttc): float
    {
        if ($ttc <= 0 || $cash == 0.0 || $vat <= 0) {
            return 0.0;
        }

        return self::round($cash * $vat / $ttc);
    }

    public static function vatToDeclare(float $collected, float $withheld): float
    {
        return self::round(max(0, $collected - $withheld));
    }

    /**
     * @return float|null Null when both sides are zero (no comparison).
     */
    public static function trendPercent(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.5) {
            if (abs($current) < 0.5) {
                return null;
            }

            return $current > 0 ? 100.0 : -100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * Overdue → urgent, due within $watchDays → watch, otherwise normal.
     */
    public static function paymentUrgency(?Carbon $due, Carbon $today, int $watchDays = 7): string
    {
        if (! $due) {
            return self::URGENCY_NORMAL;
        }

        $dueDay = $due->copy()->startOfDay();
        $todayDay = $today->copy()->startOfDay();

        if ($dueDay->lt($todayDay)) {
            return self::URGENCY_URGENT;
        }

        if ($dueDay->lte($todayDay->copy()->addDays($watchDays))) {
            return self::URGENCY_WATCH;
        }

        return self::URGENCY_NORMAL;
    }

    public static function isVatName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));

        return $normalized !== ''
            && (str_contains($normalized, 'tva') || str_contains($normalized, 'vat'));
    }

    public static function isVatWithholding(string $typeCode, string $typeName): bool
    {
        $code = mb_strtolower(trim($typeCode));

        if ($code === 'tva_retenue' || str_contains($code, 'tva')) {
            return true;
        }

        return self::isVatName($typeName);
    }

    /**
     * Largest-remainder percentages so slices sum to 100.
     *
     * @param  array<string, float>  $amounts
     * @return array<string, float>
     */
    public static function sharePercents(array $amounts): array
    {
        $total = array_sum($amounts);
        if ($total <= 0 || $amounts === []) {
            return array_map(fn () => 0.0, $amounts);
        }

        $raw = [];
        foreach ($amounts as $key => $amount) {
            $value = ($amount / $total) * 100;
            $raw[$key] = [
                'floor' => (int) floor($value),
                'frac' => $value - floor($value),
            ];
        }

        $assigned = array_sum(array_column($raw, 'floor'));
        $remaining = 100 - $assigned;
        uasort($raw, fn ($a, $b) => $b['frac'] <=> $a['frac']);

        $percents = [];
        foreach ($raw as $key => $row) {
            $percents[$key] = (float) $row['floor'];
        }

        foreach (array_keys($raw) as $key) {
            if ($remaining <= 0) {
                break;
            }
            $percents[$key] += 1;
            $remaining--;
        }

        return $percents;
    }

    /**
     * SVG polyline points for a sparkline.
     *
     * @param  array<int, float>  $values
     */
    public static function sparklinePoints(array $values, int $width = 120, int $height = 36): string
    {
        $count = count($values);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            $values[] = $values[0];
            $count = 2;
        }

        $min = min($values);
        $max = max($values);
        $span = $max - $min;
        if ($span < 0.5) {
            $span = 1.0;
            $min -= 0.5;
        }

        $pad = 2;
        $innerH = max(1, $height - ($pad * 2));
        $points = [];

        foreach ($values as $i => $value) {
            $x = round(($i / ($count - 1)) * $width, 2);
            $y = round($height - $pad - (($value - $min) / $span) * $innerH, 2);
            $points[] = $x.','.$y;
        }

        return implode(' ', $points);
    }
}
