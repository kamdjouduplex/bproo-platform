<?php

namespace Bproo\Platform\Desktop\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Apply downloaded package: extract → stage → promote into install root.
 * Local data preserved (.env, database/, storage/).
 */
class UpdateApplier
{
    public function __construct(
        protected RuntimeStateStore $state
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{
     *   ok:bool,
     *   message:string,
     *   staging:?string,
     *   rollback:?string,
     *   promoted:?bool,
     *   restart_required:?bool
     * }
     */
    public function apply(string $packagePath, array $manifest): array
    {
        if (! is_file($packagePath)) {
            throw new \RuntimeException("Package introuvable: {$packagePath}");
        }

        $version = (string) ($manifest['version'] ?? 'unknown');
        $stagingRel = trim((string) config('desktop.staging_path', 'desktop/staging'), '/').'/'.$version;
        $rollbackRel = trim((string) config('desktop.rollback_path', 'desktop/rollback'), '/');
        $staging = Storage::disk('local')->path($stagingRel);
        $rollback = Storage::disk('local')->path($rollbackRel);

        File::ensureDirectoryExists($staging);
        File::ensureDirectoryExists($rollback);

        $currentMarker = Storage::disk('local')->path('desktop/CURRENT_VERSION');
        if (is_file($currentMarker)) {
            File::copy($currentMarker, $rollback.'/CURRENT_VERSION.'.date('YmdHis'));
        }

        File::cleanDirectory($staging);
        $extracted = false;
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            if ($zip->open($packagePath) === true) {
                $zip->extractTo($staging);
                $zip->close();
                $extracted = true;
            }
        }

        if (! $extracted) {
            File::copy($packagePath, $staging.'/'.basename($packagePath));
        }

        $health = $this->runStagingSanity($staging);
        if (! $health['ok']) {
            File::cleanDirectory($staging);

            return [
                'ok' => false,
                'message' => 'Package invalide — staging nettoyé. '.$health['message'],
                'staging' => $staging,
                'rollback' => $rollback,
                'promoted' => false,
                'restart_required' => false,
            ];
        }

        file_put_contents($currentMarker, $version."\n");

        $pending = [
            'version' => $version,
            'staging' => $staging,
            'created_at' => now()->toIso8601String(),
            'manifest_version' => $version,
        ];
        $pendingPath = Storage::disk('local')->path('desktop/pending-promote.json');
        File::ensureDirectoryExists(dirname($pendingPath));
        file_put_contents(
            $pendingPath,
            json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $promote = $this->promoteNow($staging, $version);

        $this->state->merge([
            'app_version' => $version,
            'last_applied_version' => $version,
            'last_applied_at' => now()->toIso8601String(),
            'last_apply_staging' => $stagingRel,
            'pending_promote' => $promote['ok'] ? null : $pending,
            'last_promote_ok' => $promote['ok'],
            'last_promote_message' => $promote['message'],
        ]);

        if ($promote['ok']) {
            @unlink($pendingPath);

            return [
                'ok' => true,
                'message' => "Version {$version} installée. Redémarrez Bproo Pharma pour finaliser.",
                'staging' => $staging,
                'rollback' => $rollback,
                'promoted' => true,
                'restart_required' => true,
            ];
        }

        return [
            'ok' => true,
            'message' => "Version {$version} téléchargée. Fermez l'application et relancez le raccourci bureau pour terminer l'installation. ({$promote['message']})",
            'staging' => $staging,
            'rollback' => $rollback,
            'promoted' => false,
            'restart_required' => true,
        ];
    }

    /**
     * Called by host at startup when pending-promote.json exists.
     *
     * @return array{ok:bool,message:string}
     */
    public function promotePending(): array
    {
        $pendingPath = Storage::disk('local')->path('desktop/pending-promote.json');
        if (! is_file($pendingPath)) {
            return ['ok' => true, 'message' => 'Aucune à promouvoir.'];
        }

        $data = json_decode((string) file_get_contents($pendingPath), true);
        if (! is_array($data) || empty($data['staging'])) {
            @unlink($pendingPath);

            return ['ok' => false, 'message' => 'pending-promote.json invalide.'];
        }

        $staging = (string) $data['staging'];
        $version = (string) ($data['version'] ?? '');

        return $this->promoteNow($staging, $version);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function promoteNow(string $staging, string $version = ''): array
    {
        $appRoot = base_path();
        $script = $appRoot.DIRECTORY_SEPARATOR.'Promote-DesktopUpdate.ps1';
        if (! is_file($script)) {
            $script = $appRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'windows'
                .DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'Promote-DesktopUpdate.ps1';
        }

        if (! is_file($script)) {
            return $this->promoteViaPhp($staging, $appRoot, $version);
        }

        $ps = getenv('SystemRoot')
            ? getenv('SystemRoot').'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
            : 'powershell.exe';

        $args = [
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-File', $script,
            '-StagingDir', $staging,
            '-AppRoot', $appRoot,
            '-Version', $version,
        ];

        try {
            if (class_exists(Process::class)) {
                $result = Process::timeout(600)->run(array_merge([$ps], $args));
                if ($result->successful()) {
                    return ['ok' => true, 'message' => trim($result->output()) ?: 'promote ok'];
                }

                // Fallback PHP copy if robocopy/script failed (often ACL).
                $php = $this->promoteViaPhp($staging, $appRoot, $version);
                if ($php['ok']) {
                    return $php;
                }

                return [
                    'ok' => false,
                    'message' => trim($result->errorOutput().' '.$result->output().' '.$php['message']),
                ];
            }
        } catch (\Throwable $e) {
            $php = $this->promoteViaPhp($staging, $appRoot, $version);
            if ($php['ok']) {
                return $php;
            }

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return $this->promoteViaPhp($staging, $appRoot, $version);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function promoteViaPhp(string $staging, string $appRoot, string $version): array
    {
        $contentRoot = $this->resolveContentRoot($staging);
        $skipNames = ['.env', '.env.desktop', '.env.backup'];
        $skipDirs = ['storage', 'database', '.git', 'node_modules'];

        try {
            $this->copyTree($contentRoot, $appRoot, $skipDirs, $skipNames);
            if ($version !== '') {
                $marker = $appRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
                    .DIRECTORY_SEPARATOR.'desktop'.DIRECTORY_SEPARATOR.'CURRENT_VERSION';
                File::ensureDirectoryExists(dirname($marker));
                file_put_contents($marker, $version."\n");
            }
            $pending = $appRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'
                .DIRECTORY_SEPARATOR.'desktop'.DIRECTORY_SEPARATOR.'pending-promote.json';
            if (is_file($pending)) {
                @unlink($pending);
            }

            return ['ok' => true, 'message' => 'promote php ok'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function resolveContentRoot(string $staging): string
    {
        if (is_file($staging.DIRECTORY_SEPARATOR.'artisan')) {
            return $staging;
        }
        $entries = array_values(array_filter(scandir($staging) ?: [], fn ($n) => $n !== '.' && $n !== '..'));
        if (count($entries) === 1) {
            $child = $staging.DIRECTORY_SEPARATOR.$entries[0];
            if (is_dir($child) && is_file($child.DIRECTORY_SEPARATOR.'artisan')) {
                return $child;
            }
        }

        return $staging;
    }

    /**
     * @param  list<string>  $skipDirs
     * @param  list<string>  $skipNames
     */
    private function copyTree(string $src, string $dst, array $skipDirs, array $skipNames): void
    {
        File::ensureDirectoryExists($dst);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $srcLen = strlen($src);
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), $srcLen)), '/');
            if ($rel === '') {
                continue;
            }
            $parts = explode('/', $rel);
            if (in_array($parts[0], $skipDirs, true)) {
                continue;
            }
            if ($item->isFile() && in_array($item->getFilename(), $skipNames, true)) {
                continue;
            }

            $target = $dst.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if ($item->isDir()) {
                File::ensureDirectoryExists($target);
            } else {
                File::ensureDirectoryExists(dirname($target));
                File::copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function runStagingSanity(string $staging): array
    {
        $root = $this->resolveContentRoot($staging);
        if (! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
            return ['ok' => false, 'message' => 'artisan manquant dans le package.'];
        }
        if (! is_dir($root.DIRECTORY_SEPARATOR.'vendor') && ! is_dir($root.DIRECTORY_SEPARATOR.'app')) {
            return ['ok' => false, 'message' => 'Structure app incomplète dans le package.'];
        }

        return ['ok' => true, 'message' => 'staging ok'];
    }
}
