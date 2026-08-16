<?php

namespace School\Support;

/**
 * Catalog of school UI locales (FR default).
 * Tenant setting `enabled_locales` (JSON array) controls which ones appear.
 */
class SchoolLocaleCatalog
{
    public const ALL = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'pt' => 'Português',
        'ar' => 'العربية',
    ];

    /**
     * @return array<string, string> code => label
     */
    public static function all(): array
    {
        return self::ALL;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * Locales enabled for the current tenant (fallback: config / FR+EN).
     *
     * @return array<string, string>
     */
    public static function enabled(?object $tenant = null): array
    {
        $tenant = $tenant
            ?? (app()->bound('tenant') ? app('tenant') : null);

        $fallback = config('inovcom.supported_locales', ['fr', 'en']);
        if (! is_array($fallback) || $fallback === []) {
            $fallback = ['fr'];
        }

        $raw = $tenant && method_exists($tenant, 'getSetting')
            ? $tenant->getSetting('enabled_locales', $fallback)
            : $fallback;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : $fallback;
        }

        if (! is_array($raw) || $raw === []) {
            $raw = $fallback;
        }

        $out = [];
        foreach ($raw as $code) {
            $code = (string) $code;
            if (isset(self::ALL[$code])) {
                $out[$code] = self::ALL[$code];
            }
        }

        if ($out === []) {
            $out = ['fr' => self::ALL['fr']];
        }

        return $out;
    }

    /**
     * @param  list<string>  $codes
     */
    public static function saveEnabled(object $tenant, array $codes): void
    {
        $clean = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            if (isset(self::ALL[$code])) {
                $clean[] = $code;
            }
        }
        if ($clean === []) {
            $clean = ['fr'];
        }
        if (! in_array('fr', $clean, true)) {
            array_unshift($clean, 'fr');
        }

        if (method_exists($tenant, 'setSetting')) {
            $tenant->setSetting('enabled_locales', array_values(array_unique($clean)));
        }
    }
}
