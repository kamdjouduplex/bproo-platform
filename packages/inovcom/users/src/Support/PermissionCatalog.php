<?php

namespace InovCom\Users\Support;

use Illuminate\Support\Collection;
use InovCom\Users\Models\Permission;

/**
 * Groupe les permissions par préfixe métier pour une UI lisible.
 */
class PermissionCatalog
{
    /**
     * Préfixes orphelins / multi-modules → groupe affiché.
     *
     * @var array<string, string>
     */
    private const PREFIX_ALIASES = [
        'categories' => 'expenses',
        'reasons' => 'losses',
        'adjustments' => 'inventory',
        'segments' => 'clients',
        'credit_notes' => 'returns',
        'refunds' => 'returns',
        'customer_credits' => 'returns',
        'foreign_purchases' => 'purchases',
        'invoice_payments' => 'invoicing',
    ];

    /**
     * Libellés de secours si le module n'est pas dans config/modules.php.
     *
     * @var array<string, string>
     */
    private const FALLBACK_LABELS = [
        'users' => 'Utilisateurs & rôles',
        'roles' => 'Utilisateurs & rôles',
        'permissions' => 'Utilisateurs & rôles',
        'configuration' => 'Configuration',
        'items' => 'Articles',
        'clients' => 'Clients',
        'providers' => 'Fournisseurs',
        'sales' => 'Vente Direct',
        'stock' => 'Stock',
        'purchases' => 'Achats',
        'inventory' => 'Inventaire',
        'expenses' => 'Dépenses',
        'caisse' => 'Caisse',
        'losses' => 'Pertes',
        'debts' => 'Créances',
        'quotations' => 'Devis',
        'reservations' => 'Réservations',
        'prospects' => 'Prospects',
        'invoicing' => 'Facturation',
        'returns' => 'Retours & avoirs',
        'reporting' => 'Reporting',
        'payroll' => 'Paie',
        'attendance' => 'Présences',
        'tickets' => 'Tickets',
        'batches' => 'Lots',
        'prescriptions' => 'Ordonnances',
        'other' => 'Autres',
    ];

    /**
     * Fusionne certains préfixes sous un même groupe UI.
     *
     * @var array<string, string>
     */
    private const GROUP_MERGE = [
        'roles' => 'users',
        'permissions' => 'users',
    ];

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    public static function grouped(?Collection $permissions = null): Collection
    {
        $permissions ??= Permission::query()->orderBy('key')->get();

        return $permissions
            ->groupBy(fn (Permission $p) => self::groupKeyFor($p->key))
            ->sortKeysUsing(function (string $a, string $b) {
                return strcasecmp(self::groupLabel($a), self::groupLabel($b));
            })
            ->map(fn (Collection $items) => $items->sortBy('name')->values());
    }

    public static function groupKeyFor(string $permissionKey): string
    {
        $prefix = explode('.', $permissionKey)[0] ?: 'other';
        $prefix = self::PREFIX_ALIASES[$prefix] ?? $prefix;

        return self::GROUP_MERGE[$prefix] ?? $prefix;
    }

    public static function groupLabel(string $groupKey): string
    {
        $configLabel = config("modules.{$groupKey}.label");
        if (is_string($configLabel) && $configLabel !== '') {
            return $configLabel;
        }

        return self::FALLBACK_LABELS[$groupKey] ?? ucfirst(str_replace('_', ' ', $groupKey));
    }

    /**
     * Filtre une collection groupée par texte (nom, clé, description, libellé groupe).
     *
     * @param  Collection<string, Collection<int, Permission>>  $grouped
     * @return Collection<string, Collection<int, Permission>>
     */
    public static function filterGrouped(Collection $grouped, string $search): Collection
    {
        $term = mb_strtolower(trim($search));
        if ($term === '') {
            return $grouped;
        }

        return $grouped
            ->map(function (Collection $items, string $groupKey) use ($term) {
                $groupLabel = mb_strtolower(self::groupLabel($groupKey));
                if (str_contains($groupLabel, $term)) {
                    return $items;
                }

                return $items->filter(function (Permission $p) use ($term) {
                    return str_contains(mb_strtolower($p->name), $term)
                        || str_contains(mb_strtolower($p->key), $term)
                        || str_contains(mb_strtolower((string) $p->description), $term);
                })->values();
            })
            ->filter(fn (Collection $items) => $items->isNotEmpty());
    }
}
