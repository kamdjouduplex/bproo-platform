<?php

namespace Bproo\Platform\Desktop\Services;

/**
 * Resolves and validates the local desktop licence from state.json + HMAC token.
 */
class LicenceGuard
{
    public function __construct(
        protected RuntimeStateStore $state,
        protected LicenceTokenVerifier $verifier,
        protected ControlCenterClient $cc,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   reason?: string,
     *   access_mode?: string,
     *   claims?: array<string, mixed>,
     *   expires_at?: string|null
     * }
     */
    public function evaluate(): array
    {
        if (! $this->verifier->signingKeyConfigured()) {
            return ['ok' => false, 'reason' => 'missing_signing_key'];
        }

        $state = $this->state->all();
        $token = trim((string) ($state['token'] ?? ''));
        $installUuid = trim((string) ($state['install_uuid'] ?? ''));
        $fingerprint = trim((string) ($state['fingerprint'] ?? ''));

        if ($token === '' || $installUuid === '' || $fingerprint === '') {
            return ['ok' => false, 'reason' => 'missing_activation'];
        }

        $claims = $this->verifier->verify($token);
        if ($claims === null) {
            return ['ok' => false, 'reason' => 'invalid_signature'];
        }

        if ((string) ($claims['install_uuid'] ?? '') !== $installUuid) {
            return ['ok' => false, 'reason' => 'install_mismatch'];
        }

        $product = strtolower((string) config('desktop.product_key', 'pharma'));
        $claimProduct = strtolower((string) ($claims['product_key'] ?? ''));
        if ($claimProduct !== '' && $claimProduct !== $product) {
            return ['ok' => false, 'reason' => 'product_mismatch'];
        }

        $computed = $this->cc->computeFingerprint();
        if ($computed !== '' && ! hash_equals($fingerprint, $computed)) {
            return ['ok' => false, 'reason' => 'fingerprint_mismatch'];
        }

        $mode = $this->verifier->accessMode($claims);

        return [
            'ok' => true,
            'access_mode' => $mode,
            'claims' => $claims,
            'expires_at' => $claims['offline_access_expires_at'] ?? null,
        ];
    }
}
