<?php

namespace App\Services;

use App\Models\DesktopInstall;
use App\Models\DesktopRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 2 desktop update channel — additive to SaaS + Phase 1 licence.
 * Server issues signed manifests; apply/rollback is executed by the desktop runtime.
 */
class DesktopUpdateService
{
    public function __construct(
        protected DesktopLicenceService $licences
    ) {}

    /**
     * @param  array{
     *   product_key:string,
     *   channel?:string,
     *   version:string,
     *   changelog?:string|null,
     *   package_url?:string|null,
     *   package_sha256?:string|null,
     *   package_size?:int|null,
     *   min_version?:string|null,
     *   mandatory?:bool,
     *   meta?:array<string,mixed>|null,
     *   created_by?:int|null,
     *   file?:UploadedFile|null
     * }  $input
     */
    public function createDraft(array $input): DesktopRelease
    {
        $this->assertTableReady();

        $productKey = strtolower(trim((string) ($input['product_key'] ?? '')));
        $channel = strtolower(trim((string) ($input['channel'] ?? DesktopRelease::CHANNEL_STABLE)));
        $version = trim((string) ($input['version'] ?? ''));

        if ($productKey === '' || $version === '') {
            throw ValidationException::withMessages([
                'version' => 'Produit et version requis.',
            ]);
        }

        if (DesktopRelease::query()
            ->where('product_key', $productKey)
            ->where('channel', $channel)
            ->where('version', $version)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'version' => 'Cette version existe déjà pour ce produit / canal.',
            ]);
        }

        $release = new DesktopRelease([
            'product_key' => $productKey,
            'channel' => $channel ?: DesktopRelease::CHANNEL_STABLE,
            'version' => $version,
            'status' => DesktopRelease::STATUS_DRAFT,
            'changelog' => $input['changelog'] ?? null,
            'package_url' => $input['package_url'] ?? null,
            'package_sha256' => $input['package_sha256'] ? strtolower((string) $input['package_sha256']) : null,
            'package_size' => $input['package_size'] ?? null,
            'min_version' => $input['min_version'] ?? null,
            'mandatory' => (bool) ($input['mandatory'] ?? false),
            'meta' => array_merge($this->defaultApplyMeta(), $input['meta'] ?? []),
            'created_by' => $input['created_by'] ?? null,
        ]);

        if (! empty($input['file']) && $input['file'] instanceof UploadedFile) {
            $this->storePackageFile($release, $input['file']);
        }

        $release->save();

        return $release->fresh();
    }

    public function storePackageFile(DesktopRelease $release, UploadedFile $file): void
    {
        $disk = (string) config('updates.disk', 'desktop_updates');
        $ext = $file->getClientOriginalExtension() ?: 'zip';
        $path = sprintf(
            '%s/%s/%s-%s.%s',
            $release->product_key ?: 'unknown',
            $release->channel ?: 'stable',
            $release->version ?: '0.0.0',
            Str::lower(Str::random(8)),
            $ext
        );

        $sha = hash_file('sha256', $file->getRealPath()) ?: null;

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        $release->fill([
            'package_disk' => $disk,
            'package_path' => $path,
            'package_size' => $file->getSize() ?: null,
            'package_sha256' => $sha,
            'package_url' => null,
        ]);
    }

    public function publish(DesktopRelease $release): DesktopRelease
    {
        $this->assertTableReady();

        if ($release->isYanked()) {
            throw ValidationException::withMessages([
                'status' => 'Impossible de republier une release yankée. Créez une nouvelle version.',
            ]);
        }

        if (! $release->hasPackage()) {
            throw ValidationException::withMessages([
                'package' => 'Ajoutez une URL de package ou un fichier avant publication.',
            ]);
        }

        if (! $release->package_sha256) {
            throw ValidationException::withMessages([
                'package_sha256' => 'SHA-256 du package requis avant publication.',
            ]);
        }

        $release->publish();

        return $release->fresh();
    }

    public function yank(DesktopRelease $release, ?string $reason = null): DesktopRelease
    {
        $this->assertTableReady();
        $release->yank($reason);

        return $release->fresh();
    }

    /**
     * @param  array{install_uuid:string,token:string,fingerprint:string,current_version:string,channel?:string,product_key?:string}  $input
     * @return array{update_available:bool,manifest?:array<string,mixed>,signed_manifest?:string,release?:DesktopRelease}
     */
    public function check(array $input): array
    {
        $this->assertTableReady();

        $install = $this->assertLicensedInstall($input);
        $current = trim((string) ($input['current_version'] ?? ''));
        if ($current === '') {
            throw ValidationException::withMessages([
                'current_version' => 'current_version est requis.',
            ]);
        }

        $productKey = strtolower((string) ($input['product_key'] ?? $install->product_key));
        $channel = strtolower((string) ($input['channel'] ?? DesktopRelease::CHANNEL_STABLE));

        /** @var DesktopRelease|null $latest */
        $latest = DesktopRelease::query()
            ->where('product_key', $productKey)
            ->where('channel', $channel)
            ->where('status', DesktopRelease::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->sort(fn (DesktopRelease $a, DesktopRelease $b) => DesktopRelease::compareVersions($b->version, $a->version))
            ->first();

        if (! $latest || DesktopRelease::compareVersions($latest->version, $current) <= 0) {
            return ['update_available' => false];
        }

        if ($latest->min_version && DesktopRelease::compareVersions($current, $latest->min_version) < 0) {
            throw ValidationException::withMessages([
                'current_version' => "Version trop ancienne. Mettez à jour manuellement vers >= {$latest->min_version}.",
            ]);
        }

        $manifest = $this->buildManifest($latest, $install);
        $signed = $this->signManifest($manifest);

        return [
            'update_available' => true,
            'manifest' => $manifest,
            'signed_manifest' => $signed,
            'release' => $latest,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildManifest(DesktopRelease $release, ?DesktopInstall $install = null): array
    {
        $ttlHours = (int) config('updates.manifest_ttl_hours', 24);
        $downloadPath = url('/api/updates/download/'.$release->uuid);

        return [
            'typ' => 'bproo.desktop.update.manifest',
            'release_uuid' => $release->uuid,
            'product_key' => $release->product_key,
            'channel' => $release->channel,
            'version' => $release->version,
            'mandatory' => (bool) $release->mandatory,
            'changelog' => $release->changelog,
            'package_sha256' => $release->package_sha256,
            'package_size' => $release->package_size,
            'package_url' => $release->package_url ?: $downloadPath,
            'download_url' => $downloadPath,
            'min_version' => $release->min_version,
            'install_uuid' => $install?->uuid,
            'apply' => $release->meta['apply'] ?? $this->defaultApplyMeta()['apply'],
            'issued_at' => now()->toIso8601String(),
            'expires_at' => now()->addHours(max(1, $ttlHours))->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function signManifest(array $manifest): string
    {
        $payload = $this->base64UrlEncode(json_encode($manifest, JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $payload, $this->signingKey(), true);

        return $payload.'.'.$this->base64UrlEncode($sig);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyManifest(string $signed): ?array
    {
        $parts = explode('.', $signed, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $sig] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->signingKey(), true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        try {
            $claims = json_decode($this->base64UrlDecode($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($claims) ? $claims : null;
    }

    /**
     * Stream or redirect package after licence + release checks.
     *
     * @param  array{install_uuid:string,token:string,fingerprint:string}  $input
     * @return array{release: DesktopRelease, mode: 'redirect'|'path', url?: string, disk?: string, path?: string}
     */
    public function resolveDownload(string $releaseUuid, array $input): array
    {
        $this->assertTableReady();
        $this->assertLicensedInstall($input);

        /** @var DesktopRelease|null $release */
        $release = DesktopRelease::query()->where('uuid', $releaseUuid)->first();
        if (! $release || ! $release->isPublished()) {
            throw ValidationException::withMessages([
                'release' => 'Release introuvable ou non publiée.',
            ]);
        }

        if ($release->package_path && $release->package_disk) {
            if (! Storage::disk($release->package_disk)->exists($release->package_path)) {
                throw ValidationException::withMessages([
                    'package' => 'Fichier package introuvable sur le stockage.',
                ]);
            }

            return [
                'release' => $release,
                'mode' => 'path',
                'disk' => $release->package_disk,
                'path' => $release->package_path,
            ];
        }

        if ($release->package_url) {
            return [
                'release' => $release,
                'mode' => 'redirect',
                'url' => $release->package_url,
            ];
        }

        throw ValidationException::withMessages([
            'package' => 'Aucun package associé à cette release.',
        ]);
    }

    /**
     * @param  array{install_uuid?:string,token?:string,fingerprint?:string}  $input
     */
    private function assertLicensedInstall(array $input): DesktopInstall
    {
        return $this->licences->assertLicensedInstall($input);
    }

    /**
     * @return array{apply: array<string, mixed>}
     */
    private function defaultApplyMeta(): array
    {
        return [
            'apply' => [
                'strategy' => 'replace_with_rollback',
                'healthcheck' => 'php artisan about',
                'keep_previous' => true,
                'rollback_on_healthcheck_fail' => true,
            ],
        ];
    }

    private function signingKey(): string
    {
        $key = (string) config('updates.signing_key', '');
        if ($key === '') {
            $key = (string) config('licence.signing_key', '');
        }
        if ($key === '') {
            $key = (string) config('app.key', 'bproo-updates-dev-key');
        }

        return $key;
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

    private function assertTableReady(): void
    {
        if (! Schema::hasTable('desktop_releases')) {
            throw new \RuntimeException('Table desktop_releases manquante. Exécutez les migrations Control Center.');
        }
    }
}
