<?php

namespace App\Livewire\Admin;

use App\Models\DesktopInstall;
use App\Models\Tenant;
use App\Services\DesktopLicenceService;
use Livewire\Component;

/**
 * Admin UI for desktop installs — additive to TenantSubscription (SaaS billing).
 */
class TenantDesktopInstalls extends Component
{
    public Tenant $tenant;

    public string $product_key = 'pharma';

    public string $label = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->product_key = $tenant->type ?: 'pharma';
    }

    public function issueInstall(): void
    {
        $this->validate([
            'product_key' => 'required|string|max:32',
            'label' => 'nullable|string|max:255',
        ]);

        try {
            $install = app(DesktopLicenceService::class)->issuePendingInstall(
                $this->tenant,
                $this->product_key,
                $this->label !== '' ? $this->label : null
            );
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());

            return;
        }

        $this->label = '';
        notify()->success('Code d’activation créé : '.$install->activation_code);
    }

    public function revokeInstall(int $installId): void
    {
        $install = DesktopInstall::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('id', $installId)
            ->first();

        if (! $install) {
            notify()->error('Installation introuvable.');

            return;
        }

        if ($install->isRevoked()) {
            notify()->warning('Déjà révoquée.');

            return;
        }

        $install->revoke('Revoked from Control Center by '.((string) (auth()->user()?->email ?? 'admin')));
        notify()->success('Installation révoquée. Les jetons existants sont invalidés.');
    }

    public function rotateCode(int $installId): void
    {
        $install = DesktopInstall::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('id', $installId)
            ->first();

        if (! $install || $install->isRevoked()) {
            notify()->error('Installation introuvable ou révoquée.');

            return;
        }

        if ($install->isActive() && $install->fingerprint_hash) {
            notify()->error('Impossible de régénérer le code d’une machine déjà activée. Révoquez puis créez une nouvelle installation.');

            return;
        }

        $install->update([
            'activation_code' => DesktopInstall::generateActivationCode(),
        ]);

        notify()->success('Nouveau code : '.$install->fresh()->activation_code);
    }

    public function render()
    {
        $graceDays = (int) config('licence.offline_grace_days', 21);
        $installs = $this->tenant->desktopInstalls()->get();

        return view('livewire.admin.tenant-desktop-installs', [
            'installs' => $installs,
            'graceDays' => $graceDays,
        ])->layout('layouts.app', [
            'title' => 'Installations desktop',
            'subtitle' => $this->tenant->name.' · '.$this->tenant->code,
        ]);
    }
}
