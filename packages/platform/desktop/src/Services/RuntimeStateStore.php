<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Persists licence/runtime state under storage/app/desktop/state.json
 */
class RuntimeStateStore
{
    public function path(): string
    {
        $relative = (string) config('desktop.state_path', 'desktop/state.json');

        return Storage::disk('local')->path($relative);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return [];
        }

        try {
            $raw = (string) file_get_contents($path);
            // PowerShell Set-Content -Encoding UTF8 writes a BOM that breaks json_decode.
            if (str_starts_with($raw, "\xEF\xBB\xBF")) {
                $raw = substr($raw, 3);
            }
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function merge(array $patch): array
    {
        $state = array_merge($this->all(), $patch);
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));
        file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return $state;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function requireActivated(): array
    {
        $state = $this->all();
        foreach (['install_uuid', 'token', 'fingerprint'] as $key) {
            if (empty($state[$key])) {
                throw new \RuntimeException(
                    "Runtime non activé (manque {$key}). Lancez: php artisan desktop:activate {code}"
                );
            }
        }

        return $state;
    }
}
