<?php

namespace Bproo\Platform\Desktop\Services;

class LicenceClient
{
    public function __construct(
        protected ControlCenterClient $cc,
        protected RuntimeStateStore $state,
        protected LicenceTokenVerifier $verifier,
        protected LocalEntitlementSync $entitlements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function activate(string $activationCode): array
    {
        $fingerprint = $this->cc->ensureFingerprint();
        $json = $this->cc->postJson('/api/licence/activate', [
            'activation_code' => strtoupper(trim($activationCode)),
            'fingerprint' => $fingerprint,
            'product_key' => $this->cc->productKey(),
            'app_version' => $this->cc->appVersion(),
            'os' => PHP_OS_FAMILY,
        ]);

        $token = (string) ($json['token'] ?? '');
        $licence = $json['licence'] ?? [];
        if ($token !== '') {
            $verified = $this->verifier->verify($token);
            if ($verified === null) {
                throw new \RuntimeException(
                    'Jeton reçu mais signature invalide. Vérifiez DESKTOP_LICENCE_SIGNING_KEY (identique au Control Center).'
                );
            }
            $licence = $verified;
            $this->entitlements->syncFromClaims($verified);
        }

        $this->state->merge([
            'install_uuid' => $licence['install_uuid'] ?? null,
            'token' => $token !== '' ? $token : null,
            'fingerprint' => $fingerprint,
            'licence' => $licence,
            'activated_at' => now()->toIso8601String(),
            'app_version' => $this->cc->appVersion(),
        ]);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(): array
    {
        $s = $this->state->requireActivated();
        $json = $this->cc->postJson('/api/licence/heartbeat', [
            'install_uuid' => $s['install_uuid'],
            'token' => $s['token'],
            'fingerprint' => $s['fingerprint'],
            'app_version' => $this->cc->appVersion(),
            'os' => PHP_OS_FAMILY,
        ]);

        $token = (string) ($json['token'] ?? $s['token']);
        $licence = $json['licence'] ?? ($s['licence'] ?? null);
        if ($token !== '') {
            $verified = $this->verifier->verify($token);
            if ($verified === null) {
                throw new \RuntimeException(
                    'Heartbeat: signature invalide. Vérifiez DESKTOP_LICENCE_SIGNING_KEY.'
                );
            }
            $licence = $verified;
            $this->entitlements->syncFromClaims($verified);
        }

        $this->state->merge([
            'token' => $token,
            'licence' => $licence,
            'last_heartbeat_at' => now()->toIso8601String(),
        ]);

        return $json;
    }
}
