<?php

use App\Models\Tenant;
use App\Services\ModuleRegistry;
use App\Services\TenantManager;

if (! function_exists('items_is_pharmacy_catalog')) {
    /**
     * True when the tenant uses the Médicaments module (vs Articles).
     */
    function items_is_pharmacy_catalog(): bool
    {
        try {
            $tenant = app(TenantManager::class)->tenant();
            if (! $tenant) {
                return false;
            }

            if (app(ModuleRegistry::class)->isEnabled('medicaments', $tenant)) {
                return true;
            }

            // Fallback: pharma product app without medicaments pivot yet
            $type = Tenant::normalizeType($tenant->getRawOriginal('type') ?? $tenant->type);

            return $type === 'pharma' && ! app(ModuleRegistry::class)->isEnabled('items', $tenant);
        } catch (\Throwable) {
            return false;
        }
    }
}

if (! function_exists('items_catalog_noun')) {
    /** @return array{singular: string, plural: string, title: string, subtitle: string, sku_prefix: string} */
    function items_catalog_noun(): array
    {
        if (items_is_pharmacy_catalog()) {
            return [
                'singular' => 'médicament',
                'plural' => 'médicaments',
                'title' => 'Médicaments',
                'subtitle' => 'Catalogue pharmacie',
                'sku_prefix' => 'MED',
            ];
        }

        return [
            'singular' => 'article',
            'plural' => 'articles',
            'title' => 'Articles',
            'subtitle' => 'Catalogue produit',
            'sku_prefix' => 'ART',
        ];
    }
}
