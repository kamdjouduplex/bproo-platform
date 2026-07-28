<?php

namespace InovCom\Items\Services;

use App\Models\Tenant;
use App\Services\TenantManager;

class ItemsListColumnService
{
    public const SETTING_KEY = 'items_list_columns';

    public static function defaultColumns(): array
    {
        return [
            ['key' => 'reference', 'label' => 'Référence', 'visible' => true, 'order' => 10],
            ['key' => 'designation', 'label' => 'Désignation', 'visible' => true, 'order' => 20],
            ['key' => 'category', 'label' => 'Catégorie', 'visible' => true, 'order' => 30],
            ['key' => 'brand', 'label' => 'Marque', 'visible' => true, 'order' => 40],
            ['key' => 'unit', 'label' => 'Unité base', 'visible' => true, 'order' => 50],
            ['key' => 'price', 'label' => 'Prix vente', 'visible' => true, 'order' => 60],
            ['key' => 'cost', 'label' => 'Coût', 'visible' => false, 'order' => 70, 'requires_permission' => 'items.view_cost'],
            ['key' => 'barcode', 'label' => 'Code-barres', 'visible' => false, 'order' => 80],
            ['key' => 'status', 'label' => 'Statut', 'visible' => true, 'order' => 90],
        ];
    }

    public function getColumns(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? app(TenantManager::class)->tenant();
        $defaults = self::defaultColumns();
        $defaultsByKey = collect($defaults)->keyBy('key');

        if (!$tenant) {
            return $this->sortColumns($defaults);
        }

        $raw = $tenant->getSetting(self::SETTING_KEY);
        if ($raw === null || $raw === '') {
            return $this->sortColumns($defaults);
        }

        $saved = json_decode((string) $raw, true);
        if (!is_array($saved)) {
            return $this->sortColumns($defaults);
        }

        $merged = [];
        foreach ($saved as $row) {
            $key = $row['key'] ?? null;
            if (!$key || !$defaultsByKey->has($key)) {
                continue;
            }
            $def = $defaultsByKey->get($key);
            $merged[] = [
                'key' => $key,
                'label' => $def['label'],
                'visible' => (bool) ($row['visible'] ?? $def['visible']),
                'order' => (int) ($row['order'] ?? $def['order']),
                'requires_permission' => $def['requires_permission'] ?? null,
            ];
        }

        foreach ($defaults as $def) {
            if (!collect($merged)->contains('key', $def['key'])) {
                $merged[] = $def;
            }
        }

        return $this->sortColumns($merged);
    }

    public function visibleColumnsForUser(?object $user, ?Tenant $tenant = null): array
    {
        return collect($this->getColumns($tenant))
            ->filter(function ($col) use ($user) {
                if (!($col['visible'] ?? true)) {
                    return false;
                }
                $perm = $col['requires_permission'] ?? null;
                if ($perm && !$this->userCan($user, $perm)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    public function saveColumns(array $columns, ?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? app(TenantManager::class)->tenant();
        if (!$tenant) {
            return;
        }

        $allowed = collect(self::defaultColumns())->keyBy('key');
        $normalized = [];

        foreach ($columns as $row) {
            $key = $row['key'] ?? null;
            if (!$key || !$allowed->has($key)) {
                continue;
            }
            $def = $allowed->get($key);
            $normalized[] = [
                'key' => $key,
                'visible' => (bool) ($row['visible'] ?? false),
                'order' => (int) ($row['order'] ?? $def['order']),
            ];
        }

        $tenant->setSetting(self::SETTING_KEY, json_encode($normalized));
    }

    public function userCan(?object $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }

    private function sortColumns(array $columns): array
    {
        return collect($columns)->sortBy('order')->values()->all();
    }
}
