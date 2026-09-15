<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Carbon;

/**
 * Offline HMAC verification of Control Center desktop licence tokens.
 * Requires DESKTOP_LICENCE_SIGNING_KEY (same secret as Control Center).
 */
class LicenceTokenVerifier
{
    /**
     * @return array<string, mixed>|null
     */
    public function verify(string $token): ?array
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

        if (! is_array($claims)) {
            return null;
        }

        if (($claims['typ'] ?? null) !== 'bproo.desktop.licence') {
            return null;
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function allowsWrite(array $claims, ?Carbon $now = null): bool
    {
        $now = $now ?: now();
        $expires = $claims['offline_access_expires_at'] ?? null;
        if (! is_string($expires) || $expires === '') {
            return false;
        }

        try {
            $at = Carbon::parse($expires);

            return $at->isFuture() || $at->greaterThanOrEqualTo($now);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Effective access mode derived from signed expiry (token always issues as read_write).
     *
     * @param  array<string, mixed>  $claims
     */
    public function accessMode(array $claims, ?Carbon $now = null): string
    {
        return $this->allowsWrite($claims, $now) ? 'read_write' : 'read_only';
    }

    public function signingKeyConfigured(): bool
    {
        return $this->signingKey() !== '';
    }

    public function signingKey(): string
    {
        // Desktop must use the shared CC secret — never fall back to local APP_KEY
        // (that would let a pirate forge tokens with their own install key).
        return (string) config('desktop.licence_signing_key', '');
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
}
