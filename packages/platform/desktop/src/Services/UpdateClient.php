<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UpdateClient
{
    public function __construct(
        protected ControlCenterClient $cc,
        protected RuntimeStateStore $state
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(?string $currentVersion = null): array
    {
        $s = $this->state->requireActivated();
        $version = $currentVersion ?: $this->cc->appVersion();

        $json = $this->cc->postJson('/api/updates/check', [
            'install_uuid' => $s['install_uuid'],
            'token' => $s['token'],
            'fingerprint' => $s['fingerprint'],
            'current_version' => $version,
            'channel' => (string) config('desktop.update_channel', 'stable'),
            'product_key' => $this->cc->productKey(),
        ]);

        $this->state->merge([
            'last_update_check_at' => now()->toIso8601String(),
            'last_update_check' => $json,
        ]);

        return $json;
    }

    /**
     * Download package bytes to local packages disk path. Returns absolute path.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function download(array $manifest): string
    {
        $s = $this->state->requireActivated();
        $version = (string) ($manifest['version'] ?? 'unknown');
        $sha = (string) ($manifest['package_sha256'] ?? '');
        $url = (string) ($manifest['download_url'] ?? $manifest['package_url'] ?? '');

        if ($url === '') {
            throw new \RuntimeException('Manifest sans URL de package.');
        }

        $relativeDir = trim((string) config('desktop.packages_path', 'desktop/packages'), '/');
        Storage::disk('local')->makeDirectory($relativeDir);
        $filename = sprintf('%s-%s.bin', $this->cc->productKey(), $version);
        $relative = $relativeDir.'/'.$filename;
        $absolute = Storage::disk('local')->path($relative);

        // Authenticated download endpoint expects query credentials.
        if (str_contains($url, '/api/updates/download/')) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $url .= $sep.http_build_query([
                'install_uuid' => $s['install_uuid'],
                'token' => $s['token'],
                'fingerprint' => $s['fingerprint'],
            ]);
        }

        File::ensureDirectoryExists(dirname($absolute));
        if (is_file($absolute)) {
            @unlink($absolute);
        }

        // Prefer same-machine copy when Control Center is local (avoids artisan serve cURL 18).
        $localPath = (string) ($manifest['package_local_path'] ?? '');
        if ($localPath !== '' && is_file($localPath) && is_readable($localPath)) {
            if (! @copy($localPath, $absolute)) {
                throw new \RuntimeException('Copie locale du package impossible.');
            }
        } else {
            // Stream to disk — never buffer ~100MB+ in PHP memory (causes cURL 18 / OOM
            // with artisan serve and Livewire).
            $response = Http::timeout(600)
                ->connectTimeout(30)
                ->withOptions([
                    'sink' => $absolute,
                    'curl' => [
                        CURLOPT_BUFFERSIZE => 256 * 1024,
                    ],
                ])
                ->withHeaders([
                    'Accept' => 'application/octet-stream',
                    'Connection' => 'close',
                ])
                ->get($url);

            if ($response->failed()) {
                @unlink($absolute);
                throw new \RuntimeException('Téléchargement package échoué (HTTP '.$response->status().').');
            }
        }

        if (! is_file($absolute) || filesize($absolute) === 0) {
            @unlink($absolute);
            throw new \RuntimeException('Téléchargement package vide ou incomplet.');
        }

        $expectedSize = (int) ($manifest['package_size'] ?? 0);
        if ($expectedSize > 0 && filesize($absolute) !== $expectedSize) {
            $got = filesize($absolute);
            @unlink($absolute);
            throw new \RuntimeException("Téléchargement incomplet ({$got}/{$expectedSize} octets). Relancez le Control Center puis réessayez.");
        }

        if ($sha !== '') {
            $actual = hash_file('sha256', $absolute);
            if (! hash_equals(strtolower($sha), strtolower((string) $actual))) {
                @unlink($absolute);
                throw new \RuntimeException('SHA-256 package invalide — fichier rejeté.');
            }
        }

        $this->state->merge([
            'last_downloaded_package' => $relative,
            'last_downloaded_version' => $version,
            'last_downloaded_sha256' => $sha,
        ]);

        return $absolute;
    }
}
