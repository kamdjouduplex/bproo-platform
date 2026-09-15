<?php

namespace App\Services;

use App\Models\DesktopInstall;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Phase 1 desktop licence plane — additive to SaaS SubscriptionService.
 * Does not change SaaS payment / hasActiveSubscription gates.
 */
class DesktopLicenceService
{
    public function issuePendingInstall(
        Tenant $tenant,
        string $productKey = 'pharma',
        ?string $label = null
    ): DesktopInstall {
        $this->assertTableReady();

        return DesktopInstall::create([
            'tenant_id' => $tenant->id,
            'product_key' => strtolower($productKey),
            'label' => $label ?: ('Install '.now()->format('Y-m-d H:i')),
            'status' => DesktopInstall::STATUS_PENDING,
        ]);
    }

    /**
     * @param  array{activation_code:string,fingerprint:string,product_key?:string,app_version?:string,os?:string,ip?:string}  $input
     * @return array{install: DesktopInstall, token: string, licence: array<string,mixed>}
     */
    public function activate(array $input): array
    {
        $this->assertTableReady();

        $code = strtoupper(trim((string) ($input['activation_code'] ?? '')));
        $fingerprint = trim((string) ($input['fingerprint'] ?? ''));
        if ($code === '' || $fingerprint === '') {
            throw ValidationException::withMessages([
                'activation_code' => 'Code d’activation et empreinte machine requis.',
            ]);
        }

        /** @var DesktopInstall|null $install */
        $install = DesktopInstall::query()
            ->where('activation_code', $code)
            ->with('tenant')
            ->first();

        if (! $install) {
            throw ValidationException::withMessages([
                'activation_code' => 'Code d’activation invalide.',
            ]);
        }

        if ($install->isRevoked()) {
            throw ValidationException::withMessages([
                'activation_code' => 'Cette installation a été révoquée.',
            ]);
        }

        $productKey = strtolower((string) ($input['product_key'] ?? $install->product_key));
        if ($productKey !== '' && $productKey !== $install->product_key) {
            throw ValidationException::withMessages([
                'product_key' => 'Produit non autorisé pour ce code.',
            ]);
        }

        $fpHash = DesktopInstall::hashFingerprint($fingerprint);
        $existingFp = DesktopInstall::query()
            ->where('tenant_id', $install->tenant_id)
            ->where('fingerprint_hash', $fpHash)
            ->where('id', '!=', $install->id)
            ->where('status', '!=', DesktopInstall::STATUS_REVOKED)
            ->exists();

        if ($existingFp) {
            throw ValidationException::withMessages([
                'fingerprint' => 'Cette machine est déjà liée à une autre installation active.',
            ]);
        }

        if ($install->fingerprint_hash && $install->fingerprint_hash !== $fpHash) {
            throw ValidationException::withMessages([
                'fingerprint' => 'Ce code est déjà lié à une autre machine.',
            ]);
        }

        $tenant = $install->tenant;
        if (! $tenant || ! $tenant->is_active) {
            throw ValidationException::withMessages([
                'activation_code' => 'Entreprise inactive. Contactez le support Afroinov.',
            ]);
        }

        if (! $tenant->hasActiveSubscription()) {
            throw ValidationException::withMessages([
                'activation_code' => 'Aucun abonnement SaaS actif pour cette entreprise.',
            ]);
        }

        $install->fill([
            'fingerprint_hash' => $fpHash,
            'status' => DesktopInstall::STATUS_ACTIVE,
            'activated_at' => $install->activated_at ?: now(),
            'last_heartbeat_at' => now(),
            'app_version' => $input['app_version'] ?? $install->app_version,
            'os' => $input['os'] ?? $install->os,
            'last_ip' => $input['ip'] ?? $install->last_ip,
        ])->save();

        return $this->tokenPayload($install->fresh(['tenant']));
    }

    /**
     * @param  array{install_uuid:string,token:string,fingerprint:string,app_version?:string,os?:string,ip?:string}  $input
     * @return array{install: DesktopInstall, token: string, licence: array<string,mixed>}
     */
    public function heartbeat(array $input): array
    {
        $this->assertTableReady();

        $uuid = trim((string) ($input['install_uuid'] ?? ''));
        $token = trim((string) ($input['token'] ?? ''));
        $fingerprint = trim((string) ($input['fingerprint'] ?? ''));

        if ($uuid === '' || $token === '' || $fingerprint === '') {
            throw ValidationException::withMessages([
                'token' => 'install_uuid, token et fingerprint sont requis.',
            ]);
        }

        /** @var DesktopInstall|null $install */
        $install = DesktopInstall::query()
            ->where('uuid', $uuid)
            ->with('tenant')
            ->first();

        if (! $install || $install->isRevoked()) {
            throw ValidationException::withMessages([
                'install_uuid' => 'Installation introuvable ou révoquée.',
            ]);
        }

        $claims = $this->verifyToken($token);
        if (! $claims
            || ($claims['install_uuid'] ?? null) !== $install->uuid
            || (int) ($claims['token_version'] ?? 0) !== (int) $install->token_version
        ) {
            throw ValidationException::withMessages([
                'token' => 'Jeton invalide ou révoqué. Réactivez l’installation.',
            ]);
        }

        $fpHash = DesktopInstall::hashFingerprint($fingerprint);
        if ($install->fingerprint_hash && $install->fingerprint_hash !== $fpHash) {
            throw ValidationException::withMessages([
                'fingerprint' => 'Empreinte machine non reconnue.',
            ]);
        }

        $tenant = $install->tenant;
        if (! $tenant || ! $tenant->is_active) {
            throw ValidationException::withMessages([
                'install_uuid' => 'Entreprise inactive.',
            ]);
        }

        // SaaS billing still drives entitlement; heartbeat refreshes offline window.
        if (! $tenant->hasActiveSubscription()) {
            throw ValidationException::withMessages([
                'install_uuid' => 'Abonnement expiré. Régularisez la facturation puis reconnectez-vous.',
            ]);
        }

        $install->fill([
            'last_heartbeat_at' => now(),
            'app_version' => $input['app_version'] ?? $install->app_version,
            'os' => $input['os'] ?? $install->os,
            'last_ip' => $input['ip'] ?? $install->last_ip,
            'status' => DesktopInstall::STATUS_ACTIVE,
        ])->save();

        return $this->tokenPayload($install->fresh(['tenant']));
    }

    /**
     * @return array{install: DesktopInstall, token: string, licence: array<string,mixed>}
     */
    public function tokenPayload(DesktopInstall $install): array
    {
        $licence = $this->buildLicenceClaims($install);
        $token = $this->signClaims($licence);

        return [
            'install' => $install,
            'token' => $token,
            'licence' => $licence,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLicenceClaims(DesktopInstall $install): array
    {
        $tenant = $install->tenant;
        $subscription = $tenant?->currentSubscription();
        $graceDays = (int) config('licence.offline_grace_days', 21);
        $heartbeatDays = (int) config('licence.heartbeat_interval_days', 7);

        $modules = [];
        if ($tenant) {
            $modules = $tenant->modules()
                ->wherePivot('enabled', true)
                ->pluck('key')
                ->filter()
                ->values()
                ->all();
        }

        $offlineExpires = $install->offlineAccessExpiresAt($graceDays);

        return [
            'typ' => 'bproo.desktop.licence',
            'tenant_id' => $tenant?->id,
            'tenant_code' => $tenant?->code,
            'product_key' => $install->product_key,
            'install_uuid' => $install->uuid,
            'token_version' => (int) $install->token_version,
            'subscription_status' => $subscription?->status,
            'period_end' => $subscription?->current_period_end?->toDateString(),
            'modules' => $modules,
            'heartbeat_interval_days' => $heartbeatDays,
            'offline_grace_days' => $graceDays,
            'offline_access_expires_at' => $offlineExpires?->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
            'access_mode' => 'read_write',
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function signClaims(array $claims): string
    {
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $payload, $this->signingKey(), true);

        return $payload.'.'.$this->base64UrlEncode($sig);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $sig] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->signingKey(), true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        try {
            $json = $this->base64UrlDecode($payload);
            $claims = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($claims) ? $claims : null;
    }

    /**
     * Helper for future desktop runtime: given last local token claims + now, is write still allowed offline?
     *
     * @param  array<string, mixed>  $claims
     */
    public function allowsOfflineWrite(array $claims, ?Carbon $now = null): bool
    {
        $now = $now ?: now();
        $expires = $claims['offline_access_expires_at'] ?? null;
        if (! is_string($expires) || $expires === '') {
            return false;
        }

        try {
            return Carbon::parse($expires)->isFuture() || Carbon::parse($expires)->greaterThanOrEqualTo($now);
        } catch (\Throwable) {
            return false;
        }
    }

    private function signingKey(): string
    {
        $key = (string) config('licence.signing_key', '');
        if ($key === '') {
            $key = (string) config('app.key', 'bproo-licence-dev-key');
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function assertTableReady(): void
    {
        if (! Schema::hasTable('desktop_installs')) {
            throw new \RuntimeException('Table desktop_installs manquante. Exécutez les migrations Control Center.');
        }
    }
}
