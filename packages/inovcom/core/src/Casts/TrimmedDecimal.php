<?php

namespace InovCom\Kernel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Decimal cast without trailing zeros in UI (10.000 → 10, 10.50 → 10.5).
 */
class TrimmedDecimal implements CastsAttributes
{
    public function __construct(protected int $decimals = 2)
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return self::trim((float) $value, $this->decimals);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => round((float) $value, $this->decimals)];
    }

    public static function trim(float $value, int $decimals): int|float
    {
        $n = round($value, $decimals);

        if (abs($n - (int) $n) < 1e-9) {
            return (int) $n;
        }

        return $n;
    }
}
