<?php

namespace App\Livewire\Admin;

use App\Models\DesktopRelease;
use App\Services\DesktopUpdateService;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Global desktop release catalogue — additive to SaaS Plans / Subscriptions.
 */
class DesktopReleasesIndex extends Component
{
    use WithFileUploads;

    public string $product_key = 'pharma';

    public string $channel = 'stable';

    public string $version = '';

    public string $changelog = '';

    public string $package_url = '';

    public string $package_sha256 = '';

    public string $min_version = '';

    public bool $mandatory = false;

    public $package_file = null;

    /** Absolute path on the Control Center machine (avoids PHP upload limits for large zips). */
    public string $package_local_path = '';

    public string $filter_product = '';

    public string $filter_channel = '';

    public string $filter_status = '';

    public function updatedPackageFile(): void
    {
        try {
            $this->validate([
                'package_file' => 'nullable|file|max:524288', // 512 MB (KB) — still capped by PHP ini
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->package_file = null;
            notify()->error(collect($e->errors())->flatten()->first() ?: 'Fichier refusé (taille / type).');
            throw $e;
        }
    }

    public function createDraft(): void
    {
        $this->validate([
            'product_key' => 'required|string|max:32',
            'channel' => 'required|in:stable,beta',
            'version' => 'required|string|max:64',
            'changelog' => 'nullable|string|max:5000',
            'package_url' => 'nullable|url|max:2048',
            'package_sha256' => 'nullable|string|size:64',
            'min_version' => 'nullable|string|max:64',
            'mandatory' => 'boolean',
            'package_file' => 'nullable|file|max:524288',
            'package_local_path' => 'nullable|string|max:1024',
        ]);

        $localPath = trim($this->package_local_path);
        if (! $this->package_url && ! $this->package_file && $localPath === '') {
            notify()->error('Fournissez une URL, un fichier, ou un chemin local serveur vers le zip.');

            return;
        }

        if ($localPath !== '' && (! is_file($localPath) || ! is_readable($localPath))) {
            notify()->error('Chemin local introuvable ou illisible : '.$localPath);

            return;
        }

        try {
            app(DesktopUpdateService::class)->createDraft([
                'product_key' => $this->product_key,
                'channel' => $this->channel,
                'version' => $this->version,
                'changelog' => $this->changelog !== '' ? $this->changelog : null,
                'package_url' => $this->package_url !== '' ? $this->package_url : null,
                'package_sha256' => $this->package_sha256 !== '' ? $this->package_sha256 : null,
                'min_version' => $this->min_version !== '' ? $this->min_version : null,
                'mandatory' => $this->mandatory,
                'created_by' => auth()->id(),
                'file' => $this->package_file,
                'local_path' => $localPath !== '' ? $localPath : null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            notify()->error(collect($e->errors())->flatten()->first() ?: $e->getMessage());

            return;
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());

            return;
        }

        $this->reset(['version', 'changelog', 'package_url', 'package_sha256', 'min_version', 'mandatory', 'package_file', 'package_local_path']);
        notify()->success('Brouillon de release créé.');
    }

    public function publish(int $releaseId): void
    {
        $release = DesktopRelease::find($releaseId);
        if (! $release) {
            notify()->error('Release introuvable.');

            return;
        }

        try {
            app(DesktopUpdateService::class)->publish($release);
        } catch (\Illuminate\Validation\ValidationException $e) {
            notify()->error(collect($e->errors())->flatten()->first() ?: $e->getMessage());

            return;
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());

            return;
        }

        notify()->success("Release {$release->version} publiée.");
    }

    public function yank(int $releaseId): void
    {
        $release = DesktopRelease::find($releaseId);
        if (! $release) {
            notify()->error('Release introuvable.');

            return;
        }

        try {
            app(DesktopUpdateService::class)->yank($release, 'Yanked from Control Center by '.(auth()->user()?->email ?? 'admin'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            notify()->error(collect($e->errors())->flatten()->first() ?: $e->getMessage());

            return;
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());

            return;
        }

        notify()->success("Release {$release->version} retirée (yank).");
    }

    public function render()
    {
        $query = DesktopRelease::query()->orderByDesc('id');

        if ($this->filter_product !== '') {
            $query->where('product_key', $this->filter_product);
        }
        if ($this->filter_channel !== '') {
            $query->where('channel', $this->filter_channel);
        }
        if ($this->filter_status !== '') {
            $query->where('status', $this->filter_status);
        }

        return view('livewire.admin.desktop-releases-index', [
            'releases' => $query->limit(100)->get(),
        ])->layout('layouts.app', [
            'title' => 'Mises à jour desktop',
            'subtitle' => 'Canal Phase 2 — manifestes signés',
        ]);
    }
}
