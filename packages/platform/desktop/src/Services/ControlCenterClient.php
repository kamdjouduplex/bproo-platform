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

        return $this->computeFingerprint();
    }

    /**
     * Always recompute soft machine fingerprint (do not trust stored value).
     */
    public function computeFingerprint(): string
    {
        $raw = implode('|', [
            php_uname('n'),
            php_uname('s'),
            get_current_user() ?: 'user',
            (string) config('desktop.product_key'),
        ]);

        return hash('sha256', $raw);
    }

    /**
     * Soft machine fingerprint helper for first activation.
     */
    public function ensureFingerprint(): string
    {
        $fp = $this->computeFingerprint();
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
