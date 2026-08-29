<?php

namespace InovCom\Reporting\Support;

class ReportFigures
{
    public static function francs(float $value): float
    {
        return round($value, 0, PHP_ROUND_HALF_UP);
    }

    public static function marginPct(float $revenue, float $cost): ?float
    {
        if ($revenue <= 0) {
            return null;
        }

        return round(($revenue - $cost) / $revenue * 100, 1);
    }

    public static function sharePct(float $part, float $total): ?float
    {
        if ($total <= 0) {
            return null;
        }

        return round($part / $total * 100, 1);
    }
}
