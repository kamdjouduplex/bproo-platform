<?php

namespace Pressing\Support;

use Pressing\Models\ArticleType;

final class PressingBilling
{
    public const MODE_FIXED = 'fixed';

    public const MODE_WEIGHT_GLOBAL = 'weight_global';

    public const MODE_WEIGHT_BY_TYPE = 'weight_by_type';

    /** Lignes à prix fixe et/ou au kilo sur la même commande. */
    public const MODE_MIXED = 'mixed';

    /** Tarif unitaire par article (quantité × prix). */
    public const ARTICLE_FIXED = 'fixed';

    /** Tarif au kilo propre à ce type d'article. */
    public const ARTICLE_PER_KG = 'per_kg';

    /** @return array<string, string> */
    public static function modes(): array
    {
        return [
            self::MODE_MIXED => 'Mixte (pièce + kilo)',
            self::MODE_FIXED => 'Prix fixe uniquement',
            self::MODE_WEIGHT_BY_TYPE => 'Au kilo par type uniquement',
            self::MODE_WEIGHT_GLOBAL => 'Au kilo — tout cou',
        ];
    }

    /** @return array<string, string> */
    public static function articleModes(): array
    {
        return [
            self::ARTICLE_FIXED => 'Prix fixe',
            self::ARTICLE_PER_KG => 'Au kilo',
        ];
    }

    public static function modeLabel(string $mode): string
    {
        return self::modes()[$mode] ?? self::articleModes()[$mode] ?? $mode;
    }

    public static function modeDescription(string $mode): string
    {
        return match ($mode) {
            self::MODE_MIXED => 'Sur une même commande : chemises au prix fixe et rideaux au kilo, par exemple.',
            self::MODE_FIXED => 'Tous les articles au prix unitaire (pas de lignes au kilo).',
            self::MODE_WEIGHT_GLOBAL => 'Tout le lot est pesé une seule fois — un seul prix fixe au kilo (tout cou).',
            self::MODE_WEIGHT_BY_TYPE => 'Tous les articles au kilo (chaque type avec son prix/kg).',
            default => '',
        };
    }

    public static function articleModeDescription(string $mode): string
    {
        return match ($mode) {
            self::ARTICLE_FIXED => 'Utilisé pour les réceptions en mode prix fixe.',
            self::ARTICLE_PER_KG => 'Utilisé pour les réceptions « au kilo par type ».',
            default => '',
        };
    }

    public static function defaultMode(): string
    {
        $mode = (string) PressingSettings::get(PressingSettings::KEY_BILLING_DEFAULT_MODE, self::MODE_MIXED);

        return array_key_exists($mode, self::modes()) ? $mode : self::MODE_MIXED;
    }

    public static function globalWeightPrice(?int $agenceId = null): float
    {
        $global = (float) PressingSettings::get(PressingSettings::KEY_WEIGHT_PRICE_GLOBAL, 0);
        if ($agenceId) {
            $specific = PressingSettings::get(PressingSettings::KEY_WEIGHT_PRICE_GLOBAL . '.agence.' . $agenceId);
            if ($specific !== null && $specific !== '') {
                return (float) $specific;
            }
        }

        return max(0, $global);
    }

    public static function resolveArticleMode(ArticleType $type, ?int $agenceId = null): string
    {
        $row = $type->priceRowForAgence($agenceId);
        $mode = $row?->pricing_mode ?: $type->pricing_mode ?: self::ARTICLE_FIXED;

        return array_key_exists($mode, self::articleModes()) ? $mode : self::ARTICLE_FIXED;
    }

    public static function isTypeCompatibleWithOrderMode(ArticleType $type, string $orderMode, ?int $agenceId = null): bool
    {
        if ($orderMode === self::MODE_WEIGHT_GLOBAL) {
            return true;
        }

        return match ($orderMode) {
            self::MODE_FIXED => $type->priceForAgence($agenceId) > 0,
            self::MODE_WEIGHT_BY_TYPE => $type->pricePerKgForAgence($agenceId) > 0,
            self::MODE_MIXED => self::hasFixedPrice($type, $agenceId) || self::hasPerKgPrice($type, $agenceId),
            default => true,
        };
    }

    public static function hasFixedPrice(ArticleType $type, ?int $agenceId = null): bool
    {
        return $type->priceForAgence($agenceId) > 0;
    }

    public static function hasPerKgPrice(ArticleType $type, ?int $agenceId = null): bool
    {
        return $type->pricePerKgForAgence($agenceId) > 0;
    }

    /**
     * Suggested line pricing when a type is selected in mixed mode.
     * Keeps the current line preference if still valid.
     */
    public static function suggestLinePricingMode(
        ArticleType $type,
        ?int $agenceId = null,
        ?string $currentLineMode = null
    ): string {
        $hasFixed = self::hasFixedPrice($type, $agenceId);
        $hasKg = self::hasPerKgPrice($type, $agenceId);

        if ($currentLineMode === self::ARTICLE_PER_KG && $hasKg) {
            return self::ARTICLE_PER_KG;
        }
        if ($currentLineMode === self::ARTICLE_FIXED && $hasFixed) {
            return self::ARTICLE_FIXED;
        }
        if ($hasFixed && $hasKg) {
            return self::resolveArticleMode($type, $agenceId) === self::ARTICLE_PER_KG
                ? self::ARTICLE_PER_KG
                : self::ARTICLE_FIXED;
        }

        return $hasKg ? self::ARTICLE_PER_KG : self::ARTICLE_FIXED;
    }

    /** Resolve how a single line is billed (fixed vs weight_by_type). */
    public static function effectiveLineMode(string $orderMode, array $item): string
    {
        if ($orderMode === self::MODE_WEIGHT_GLOBAL) {
            return self::MODE_WEIGHT_GLOBAL;
        }

        if ($orderMode === self::MODE_MIXED) {
            $lineMode = (string) ($item['pricing_mode'] ?? self::ARTICLE_FIXED);

            return in_array($lineMode, [self::ARTICLE_PER_KG, self::MODE_WEIGHT_BY_TYPE], true)
                ? self::MODE_WEIGHT_BY_TYPE
                : self::MODE_FIXED;
        }

        return $orderMode === self::MODE_WEIGHT_BY_TYPE
            ? self::MODE_WEIGHT_BY_TYPE
            : self::MODE_FIXED;
    }

    public static function isLinePerKg(string $orderMode, array $item): bool
    {
        return self::effectiveLineMode($orderMode, $item) === self::MODE_WEIGHT_BY_TYPE;
    }

    public static function lineTotal(string $mode, array $item): float
    {
        $lineMode = self::effectiveLineMode($mode, $item);

        return match ($lineMode) {
            self::MODE_WEIGHT_BY_TYPE => round(
                max(0, (float) ($item['weight_kg'] ?? 0)) * max(0, (float) ($item['price_per_kg'] ?? 0)),
                2
            ),
            default => round(
                max(0, (int) ($item['quantity'] ?? 0)) * max(0, (float) ($item['unit_price'] ?? 0)),
                2
            ),
        };
    }

    public static function orderSubtotal(string $mode, array $items, ?float $totalWeightKg, ?float $weightUnitPrice): float
    {
        if ($mode === self::MODE_WEIGHT_GLOBAL) {
            return round(max(0, (float) $totalWeightKg) * max(0, (float) $weightUnitPrice), 2);
        }

        $total = 0.0;
        foreach ($items as $item) {
            $total += self::lineTotal($mode, $item);
        }

        return round($total, 2);
    }

    public static function storedItemPricingMode(string $orderMode, array $item): string
    {
        if ($orderMode === self::MODE_WEIGHT_GLOBAL) {
            return self::MODE_WEIGHT_GLOBAL;
        }

        return self::isLinePerKg($orderMode, $item)
            ? self::ARTICLE_PER_KG
            : self::ARTICLE_FIXED;
    }
}
