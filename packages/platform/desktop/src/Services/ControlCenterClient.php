<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ControlCenterClient
{
    public function __construct(
        protected RuntimeStateStore $state
    ) {}

    public function baseUrl(): string
    {
        return rtrim((string) config('desktop.control_center_url'), '/');
    }

    public function fingerprint(): string
    {
        $stored = (string) $this->state->get('fingerprint', '');
        if ($stored !== '') {
            return $stored;
        }

        $raw = implode('|', [
            php_uname('n'),
            php_uname('s'),
            get_current_user() ?: 'user',
            (string) config('desktop.product_key'),
        ]);

        return hash('sha256', $raw);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $body): array
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(60)
            ->post($url, $body);

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException("Réponse invalide de {$url} (HTTP {$response->status()})");
        }

        if ($response->failed() || ($json['ok'] ?? false) !== true) {
            $errors = $json['errors'] ?? null;
            $msg = is_array($errors)
                ? collect($errors)->flatten()->implode(' ')
                : ("HTTP {$response->status()}");
            throw new \RuntimeException(trim("Control Center: {$msg}"));
        }

        return $json;
    }

    /**
     * Soft machine fingerprint helper for first activation.
     */
    public function ensureFingerprint(): string
    {
        $fp = $this->fingerprint();
        $this->state->merge(['fingerprint' => $fp]);

        return $fp;
    }

    public function productKey(): string
    {
        return strtolower((string) config('desktop.product_key', 'pharma'));
    }

    public function appVersion(): string
    {
        return (string) ($this->state->get('app_version') ?: config('desktop.app_version', '0.1.0'));
    }

    public function newBatchUuid(): string
    {
        return (string) Str::uuid();
    }
}
