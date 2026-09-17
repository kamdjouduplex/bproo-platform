<?php

namespace Bproo\Platform\Desktop\Http\Livewire;

use Bproo\Platform\Desktop\Services\ControlCenterClient;
use Bproo\Platform\Desktop\Services\RuntimeStateStore;
use Bproo\Platform\Desktop\Services\UpdateApplier;
use Bproo\Platform\Desktop\Services\UpdateClient;
use Livewire\Component;

class DesktopUpdates extends Component
{
    public string $status = 'idle';

    public string $message = '';

    public ?string $availableVersion = null;

    public ?string $changelog = null;

    public bool $updateAvailable = false;

    public bool $restartRequired = false;

    public string $currentVersion = '';

    public ?string $lastCheckAt = null;

    public function mount(ControlCenterClient $cc, RuntimeStateStore $state): void
    {
        $this->currentVersion = $cc->appVersion();
        $s = $state->all();
        $this->lastCheckAt = isset($s['last_update_check_at']) ? (string) $s['last_update_check_at'] : null;
        $last = $s['last_update_check'] ?? null;
        if (is_array($last) && ($last['update_available'] ?? false)) {
            $this->updateAvailable = true;
            $this->availableVersion = (string) ($last['manifest']['version'] ?? $last['version'] ?? '');
            $this->changelog = (string) ($last['manifest']['changelog'] ?? $last['changelog'] ?? '');
        }
        if (! empty($s['pending_promote']) || is_file(storage_path('app/desktop/pending-promote.json'))) {
            $this->restartRequired = true;
            $this->message = 'Une mise à jour est prête. Fermez l’application et relancez le raccourci bureau.';
            $this->status = 'restart';
        }
    }

    public function checkForUpdates(UpdateClient $updates, ControlCenterClient $cc): void
    {
        $this->status = 'checking';
        $this->message = '';
        $this->restartRequired = false;

        try {
            $result = $updates->check();
            $this->currentVersion = $cc->appVersion();
            $this->lastCheckAt = now()->toIso8601String();
            $this->updateAvailable = (bool) ($result['update_available'] ?? false);

            if (! $this->updateAvailable) {
                $this->availableVersion = null;
                $this->changelog = null;
                $this->status = 'uptodate';
                $this->message = 'Votre application est à jour (v'.$this->currentVersion.').';

                return;
            }

            $manifest = $result['manifest'] ?? [];
            $this->availableVersion = (string) ($manifest['version'] ?? $result['version'] ?? '');
            $this->changelog = (string) ($manifest['changelog'] ?? $result['changelog'] ?? '');
            $this->status = 'available';
            $this->message = 'Nouvelle version disponible : v'.$this->availableVersion;
        } catch (\Throwable $e) {
            $this->status = 'error';
            $this->message = $e->getMessage();
        }
    }

    public function installUpdate(UpdateClient $updates, UpdateApplier $applier, ControlCenterClient $cc): void
    {
        $this->status = 'installing';
        $this->message = 'Téléchargement en cours…';
        $this->restartRequired = false;

        try {
            $check = $updates->check();
            if (! ($check['update_available'] ?? false)) {
                $this->status = 'uptodate';
                $this->updateAvailable = false;
                $this->message = 'Aucune mise à jour à installer.';

                return;
            }

            $manifest = $check['manifest'] ?? [];
            $path = $updates->download($manifest);
            $this->message = 'Installation en cours…';
            $result = $applier->apply($path, $manifest);

            if (! ($result['ok'] ?? false)) {
                $this->status = 'error';
                $this->message = $result['message'] ?? 'Échec de l’installation.';

                return;
            }

            $this->currentVersion = (string) ($manifest['version'] ?? $cc->appVersion());
            $this->updateAvailable = false;
            $this->restartRequired = (bool) ($result['restart_required'] ?? true);
            $this->status = 'done';
            $this->message = $result['message'] ?? 'Mise à jour installée.';
        } catch (\Throwable $e) {
            $this->status = 'error';
            $this->message = $e->getMessage();
        }
    }

    public function render()
    {
        return view('desktop::livewire.updates')
            ->layout('layouts.app', [
                'title' => 'Mises à jour',
                'subtitle' => 'Bproo Pharma Desktop',
            ]);
    }
}
