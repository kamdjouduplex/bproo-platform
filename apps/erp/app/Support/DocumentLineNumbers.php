<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\TenantManager;

class DocumentLineNumbers
{
    public static function increment(?Tenant $tenant = null): int
    {
        $tenant = $tenant ?? app(TenantManager::class)->tenant();

        return max(1, (int) ($tenant?->getSetting('document_line_increment', 10) ?? 10));
    }

    /**
     * @param  array<int, int|null>  $existing
     */
    public static function nextNumber(array $existing): int
    {
        $increment = self::increment();
        $numbers = array_values(array_filter(array_map('intval', $existing)));

        if ($numbers === []) {
            return $increment;
        }

        return max($numbers) + $increment;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cart
     * @return array<int, array<string, mixed>>
     */
    public static function assignMissing(array $cart): array
    {
        $next = self::nextNumber(array_map(
            fn ($row) => isset($row['line_number']) ? (int) $row['line_number'] : null,
            $cart
        ));

        foreach ($cart as $i => $row) {
            if (!isset($row['line_number']) || (int) $row['line_number'] <= 0) {
                $cart[$i]['line_number'] = $next;
                $next += self::increment();
            }
        }

        return $cart;
    }
}
