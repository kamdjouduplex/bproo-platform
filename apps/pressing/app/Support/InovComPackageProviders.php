<?php

namespace App\Support;

/**
 * Enregistre les ServiceProviders des packages path packages/inovcom/*
 * même si composer update / package:discover n'a pas encore été relancé.
 *
 * Sans ça, un module activé depuis l'admin peut avoir les permissions
 * mais rester invisible au menu (Route::has = false).
 */
class InovComPackageProviders
{
    /**
     * @return list<class-string>
     */
    public static function discover(): array
    {
        $providers = [];
        $pattern = base_path('packages/inovcom/*/composer.json');

        foreach (glob($pattern) ?: [] as $composerFile) {
            $json = json_decode((string) file_get_contents($composerFile), true);
            if (! is_array($json)) {
                continue;
            }

            foreach ($json['extra']['laravel']['providers'] ?? [] as $provider) {
                if (is_string($provider) && $provider !== '') {
                    $providers[] = $provider;
                }
            }
        }

        return array_values(array_unique($providers));
    }

    public static function register(\Illuminate\Contracts\Foundation\Application $app): void
    {
        foreach (self::discover() as $provider) {
            if (! class_exists($provider)) {
                continue;
            }

            // register() ignore les doublons déjà chargés (package discovery / config/app.php).
            $app->register($provider);
        }
    }
}
