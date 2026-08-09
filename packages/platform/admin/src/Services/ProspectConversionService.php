<?php

namespace App\Services;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProspectConversionService
{
    /**
     * Convert a platform prospect into a provisioned company.
     */
    public function convert(
        PlatformProspect $prospect,
        string $code,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        ?string $productType = null
    ): Tenant {
        if ($prospect->converted_tenant_id) {
            throw new \RuntimeException('Ce prospect est déjà converti.');
        }

        $type = Tenant::normalizeType($productType ?: ($prospect->product_interest ?: config('tenant_types.default', 'erp')));
        $code = Str::slug(strtolower(trim($code)), '_');
        if ($code === '') {
            throw new \InvalidArgumentException('Code entreprise invalide.');
        }
        if (Tenant::where('code', $code)->exists()) {
            throw new \InvalidArgumentException("Le code « {$code} » existe déjà.");
        }

        $prefix = (string) (config("tenant_types.types.{$type}.db_prefix") ?? 'erp');
        do {
            $dbName = $prefix . '_' . $code . '_' . strtolower(Str::random(4));
        } while (Tenant::where('db_name', $dbName)->exists());

        return DB::transaction(function () use ($prospect, $code, $dbName, $type, $adminName, $adminEmail, $adminPassword) {
            $tenant = Tenant::create([
                'name' => $prospect->company_name,
                'code' => $code,
                'db_name' => $dbName,
                'is_active' => true,
                'type' => $type,
                'contact_key_first_name' => $this->splitName($prospect->contact_name)['first'],
                'contact_key_last_name' => $this->splitName($prospect->contact_name)['last'],
                'contact_key_phone' => $prospect->contact_phone,
                'country' => $prospect->country,
                'city' => $prospect->city,
                'provisioning_status' => 'pending',
                'multi_store_setup_status' => 'disabled',
            ]);

            $prospect->update([
                'stage' => PlatformProspect::STAGE_WON,
                'converted_tenant_id' => $tenant->id,
                'converted_at' => now(),
                'product_interest' => $type,
            ]);

            PlatformProspectActivity::create([
                'platform_prospect_id' => $prospect->id,
                'user_id' => auth()->id(),
                'type' => 'convert',
                'subject' => 'Conversion en entreprise',
                'body' => "Entreprise « {$tenant->code} » créée (app {$type}). Provisionnement lancé.",
            ]);

            app(TenantProvisionDispatcher::class)->dispatch($tenant, $adminName, $adminEmail, $adminPassword);

            return $tenant;
        });
    }

    /**
     * Link an already-created tenant back to the CRM prospect (after form create).
     */
    public function attachTenant(PlatformProspect $prospect, Tenant $tenant): void
    {
        if ($prospect->converted_tenant_id) {
            throw new \RuntimeException('Ce prospect est déjà converti.');
        }

        $prospect->update([
            'stage' => PlatformProspect::STAGE_WON,
            'converted_tenant_id' => $tenant->id,
            'converted_at' => now(),
            'product_interest' => Tenant::normalizeType($tenant->getRawOriginal('type') ?? $tenant->type),
        ]);

        PlatformProspectActivity::create([
            'platform_prospect_id' => $prospect->id,
            'user_id' => auth()->id(),
            'type' => 'convert',
            'subject' => 'Conversion en entreprise',
            'body' => "Entreprise « {$tenant->code} » créée (app {$tenant->type}). Provisionnement lancé.",
        ]);
    }

    /** @return array{first: string, last: string} */
    public function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['first' => '', 'last' => ''];
        }
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];

        return [
            'first' => $parts[0] ?? '',
            'last' => $parts[1] ?? '',
        ];
    }
}
