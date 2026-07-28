<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantBrandingService
{
    public const LOGO_MAIN = 'main';

    public const LOGO_ICON = 'icon';

    private const SETTING_MAIN = 'tenant_logo_path';

    private const SETTING_ICON = 'tenant_logo_icon_path';

    public function path(?Tenant $tenant, string $type = self::LOGO_MAIN): ?string
    {
        if (!$tenant) {
            return null;
        }

        $setting = $type === self::LOGO_ICON ? self::SETTING_ICON : self::SETTING_MAIN;
        $path = (string) $tenant->getSetting($setting, '');

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    public function url(?Tenant $tenant, string $type = self::LOGO_MAIN): ?string
    {
        $path = $this->path($tenant, $type);
        if (!$path) {
            return null;
        }

        // Chemin relatif : fonctionne quel que soit le host (127.0.0.1 vs localhost) tant que public/storage est lié.
        return '/storage/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Logo encodé en data URI — fiable pour l'impression navigateur (Ctrl+P).
     */
    public function embedSrc(?Tenant $tenant, string $type = self::LOGO_MAIN): ?string
    {
        $path = $this->path($tenant, $type);
        if (!$path) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        if (!is_readable($absolute)) {
            return null;
        }

        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => @mime_content_type($absolute) ?: 'image/png',
        };

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    public function store(Tenant $tenant, UploadedFile $file, string $type = self::LOGO_MAIN): string
    {
        $this->delete($tenant, $type);

        $directory = $this->directory($tenant);
        Storage::disk('public')->makeDirectory($directory);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'png';
        $filename = ($type === self::LOGO_ICON ? 'icon' : 'logo') . '.' . $extension;
        $storedPath = $file->storeAs($directory, $filename, 'public');

        $setting = $type === self::LOGO_ICON ? self::SETTING_ICON : self::SETTING_MAIN;
        $tenant->setSetting($setting, $storedPath);

        return $storedPath;
    }

    public function delete(?Tenant $tenant, string $type = self::LOGO_MAIN): void
    {
        if (!$tenant) {
            return;
        }

        $path = $this->path($tenant, $type);
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        $setting = $type === self::LOGO_ICON ? self::SETTING_ICON : self::SETTING_MAIN;
        $tenant->setSetting($setting, '');
    }

    /**
     * Settings array for printed documents (factures, devis, tickets, etc.).
     */
    public function documentSettings(?Tenant $tenant): array
    {
        if (!$tenant) {
            return [
                'shop_name' => 'Inov-Com',
                'currency' => 'XOF',
                'logo_url' => null,
                'logo_embed_src' => null,
                'logo_icon_url' => null,
                'logo_absolute_path' => null,
            ];
        }

        $mainPath = $this->path($tenant, self::LOGO_MAIN);

        return [
            'shop_name' => (string) $tenant->getSetting('shop_name', $tenant->name ?? ''),
            'shop_tagline' => (string) $tenant->getSetting('shop_tagline', ''),
            'shop_phone' => (string) $tenant->getSetting('shop_phone', ''),
            'shop_address' => (string) $tenant->getSetting('shop_address', ''),
            'shop_bp' => (string) $tenant->getSetting('shop_bp', ''),
            'shop_email' => (string) $tenant->getSetting('shop_email', ''),
            'shop_tax_id' => (string) $tenant->getSetting('shop_tax_id', ''),
            'shop_cnps' => (string) $tenant->getSetting('shop_cnps', ''),
            'shop_rccm' => (string) $tenant->getSetting('shop_rccm', ''),
            'shop_website' => (string) $tenant->getSetting('shop_website', ''),
            'invoice_footer' => (string) $tenant->getSetting('invoice_footer', ''),
            'payment_modes_default' => (string) $tenant->getSetting('payment_modes_default', 'chèque/virement/espèces'),
            'currency' => (string) $tenant->getSetting('currency', 'XOF'),
            'invoice_prefix_declared' => (string) $tenant->getSetting('invoice_prefix_declared', 'FTH'),
            'invoice_prefix_non_declared' => (string) $tenant->getSetting('invoice_prefix_non_declared', 'FTN'),
            'print_show_header_company_info' => filter_var(
                $tenant->getSetting('print_show_header_company_info', true),
                FILTER_VALIDATE_BOOLEAN
            ),
            'logo_url' => $this->url($tenant, self::LOGO_MAIN),
            'logo_embed_src' => $this->embedSrc($tenant, self::LOGO_MAIN),
            'logo_icon_url' => $this->url($tenant, self::LOGO_ICON) ?? $this->url($tenant, self::LOGO_MAIN),
            'logo_absolute_path' => $mainPath ? Storage::disk('public')->path($mainPath) : null,
        ];
    }

    /**
     * Filigrane facture selon statut paiement / cycle de vie.
     *
     * @return array{label: string, variant: string}|null
     */
    public function invoiceWatermark(string $status): ?array
    {
        return match ($status) {
            'paid' => ['label' => 'PAYÉE', 'variant' => 'paid'],
            'partial' => ['label' => 'PARTIELLEMENT PAYÉE', 'variant' => 'partial'],
            'issued' => ['label' => 'IMPAYÉE', 'variant' => 'unpaid'],
            'cancelled' => ['label' => 'ANNULÉE', 'variant' => 'cancelled'],
            'draft' => ['label' => 'BROUILLON', 'variant' => 'draft'],
            default => null,
        };
    }

    /**
     * Filigrane devis selon statut workflow.
     *
     * @return array{label: string, variant: string}|null
     */
    public function quotationWatermark(string $status): ?array
    {
        return match ($status) {
            'accepted', 'validated' => ['label' => 'ACCEPTÉ', 'variant' => 'accepted'],
            'rejected' => ['label' => 'REJETÉ', 'variant' => 'rejected'],
            'suspended' => ['label' => 'SUSPENDU', 'variant' => 'suspended'],
            'sent' => ['label' => 'ENVOYÉ', 'variant' => 'sent'],
            'draft' => ['label' => 'BROUILLON', 'variant' => 'draft'],
            default => null,
        };
    }

    /**
     * Filigrane bon de livraison.
     *
     * @return array{label: string, variant: string}|null
     */
    public function deliveryNoteWatermark(string $status): ?array
    {
        return match ($status) {
            'confirmed' => ['label' => 'LIVRÉ', 'variant' => 'validated'],
            'cancelled' => ['label' => 'ANNULÉ', 'variant' => 'cancelled'],
            'draft' => ['label' => 'BROUILLON', 'variant' => 'draft'],
            default => null,
        };
    }

    /**
     * Filigrane vente POS (paiement immédiat ou crédit).
     *
     * @return array{label: string, variant: string}|null
     */
    public function saleWatermark(bool $fullyPaid, bool $hasCreditPayment): ?array
    {
        if ($fullyPaid) {
            return ['label' => 'PAYÉE', 'variant' => 'paid'];
        }

        if ($hasCreditPayment) {
            return ['label' => 'À CRÉDIT', 'variant' => 'unpaid'];
        }

        return null;
    }

    private function directory(Tenant $tenant): string
    {
        $code = Str::slug($tenant->code ?: (string) $tenant->id, '_');

        return 'tenants/' . $code . '/branding';
    }
}
