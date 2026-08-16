<?php

namespace App\Services;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dispatches tenant provisioning on the product app that owns the tenant type.
 * Control Center (and other hosts) call the product via signed HTTP; the product
 * app runs ProvisionTenantJob locally so module migrations are available.
 */
class TenantProvisionDispatcher
{
    public function dispatch(
        Tenant $tenant,
        string $adminName,
        string $adminEmail,
        string $adminPassword
    ): void {
        $type = Tenant::normalizeType($tenant->getRawOriginal('type') ?? $tenant->type);
        $baseUrl = rtrim((string) config("tenant_types.types.{$type}.base_url", ''), '/');

        if ($this->shouldProvisionLocally($type, $baseUrl)) {
            // Avoid Livewire/PHP 30s timeout: run after the HTTP response is sent.
            ProvisionTenantJob::dispatch($tenant, $adminName, $adminEmail, $adminPassword)
                ->afterResponse();

            return;
        }

        if ($baseUrl === '') {
            $tenant->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => "PRODUCT URL manquante pour le type « {$type} » (tenant_types.types.{$type}.base_url).",
            ]);

            throw new \RuntimeException("No product base_url configured for tenant type [{$type}].");
        }

        $secret = (string) config('tenant_types.provision_secret', '');
        if ($secret === '') {
            $tenant->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => 'TENANT_PROVISION_SECRET manquant — impossible de déléguer le provisionnement.',
            ]);

            throw new \RuntimeException('TENANT_PROVISION_SECRET is not configured.');
        }

        $url = $baseUrl.'/internal/tenants/provision';

        Log::info('Delegating tenant provisioning to product app', [
            'tenant_id' => $tenant->id,
            'tenant_code' => $tenant->code,
            'type' => $type,
            'url' => $url,
        ]);

        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->withToken($secret)
                ->post($url, [
                    'tenant_id' => $tenant->id,
                    'tenant_code' => $tenant->code,
                    'admin_name' => $adminName,
                    'admin_email' => $adminEmail,
                    'admin_password' => $adminPassword,
                ]);
        } catch (\Throwable $e) {
            $tenant->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => 'Délégation provisionnement échouée: '.$e->getMessage(),
            ]);

            throw $e;
        }

        if (! $response->successful()) {
            $message = 'Délégation provisionnement HTTP '.$response->status().': '.Str::limit($response->body(), 300);
            $tenant->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => $message,
            ]);

            throw new \RuntimeException($message);
        }
    }

    /**
     * Provision on this host when it is the product app for the tenant type,
     * or when APP_URL matches the product base URL (single-app / local).
     */
    public function shouldProvisionLocally(string $type, string $baseUrl): bool
    {
        $localKey = (string) config('tenant_types.local_app_key', '');
        $typeKey = (string) config("tenant_types.types.{$type}.app_key", $type);

        if ($localKey !== '' && $localKey === $typeKey) {
            return true;
        }

        if ($baseUrl === '') {
            return true;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && $this->sameOrigin($appUrl, $baseUrl)) {
            return true;
        }

        // No secret yet → keep legacy local behaviour (dev) rather than hard-fail create.
        if ((string) config('tenant_types.provision_secret', '') === '') {
            Log::warning('TENANT_PROVISION_SECRET empty; provisioning locally (legacy). Set the secret for multi-app.', [
                'type' => $type,
                'base_url' => $baseUrl,
            ]);

            return true;
        }

        return false;
    }

    private function sameOrigin(string $a, string $b): bool
    {
        $pa = parse_url($a);
        $pb = parse_url($b);
        if (! is_array($pa) || ! is_array($pb)) {
            return false;
        }

        $hostA = strtolower((string) ($pa['host'] ?? ''));
        $hostB = strtolower((string) ($pb['host'] ?? ''));
        $portA = (int) ($pa['port'] ?? (($pa['scheme'] ?? 'http') === 'https' ? 443 : 80));
        $portB = (int) ($pb['port'] ?? (($pb['scheme'] ?? 'http') === 'https' ? 443 : 80));

        return $hostA !== '' && $hostA === $hostB && $portA === $portB;
    }
}
