<?php

namespace InovCom\Kernel;

use InovCom\Kernel\Casts\TrimmedDecimal;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model for tenant-scoped data. All module models using the tenant
 * database should extend this class.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';

    protected function castAttribute($key, $value)
    {
        $castType = $this->getCasts()[$key] ?? null;

        if (is_string($castType) && str_starts_with($castType, 'decimal:')) {
            $decimals = (int) (explode(':', $castType, 2)[1] ?? 2);
            $casted = parent::castAttribute($key, $value);

            if ($casted === null) {
                return null;
            }

            return TrimmedDecimal::trim((float) $casted, $decimals);
        }

        return parent::castAttribute($key, $value);
    }
}
