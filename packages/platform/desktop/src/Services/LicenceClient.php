<?php

namespace Bproo\Platform\Desktop\Services;

class LicenceClient
{
    public function __construct(
        protected ControlCenterClient $cc,
        protected RuntimeStateStore $state
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

        $licence = $json['licence'] ?? [];
        $this->state->merge([
            'install_uuid' => $licence['install_uuid'] ?? null,
            'token' => $json['token'] ?? null,
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

        $this->state->merge([
            'token' => $json['token'] ?? $s['token'],
            'licence' => $json['licence'] ?? ($s['licence'] ?? null),
            'last_heartbeat_at' => now()->toIso8601String(),
        ]);

        return $json;
    }
}
