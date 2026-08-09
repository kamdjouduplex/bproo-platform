<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\CompanyIntelligenceService;
use Livewire\Component;

class TenantShow extends Component
{
    public Tenant $tenant;

    public array $metrics = [];

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->metrics = [
            'users_count' => $tenant->users_count,
            'modules_enabled_count' => $tenant->modules_enabled_count,
            'db_ok' => null,
            'error' => null,
            'last_tenant_activity_at' => $tenant->last_tenant_activity_at,
            'users_limit_exceeded' => (bool) $tenant->users_limit_exceeded_at,
            'users_limit_newly_exceeded' => false,
        ];
    }

    public function refreshMetrics(): void
    {
        $this->metrics = app(CompanyIntelligenceService::class)->refresh($this->tenant, true);
        $this->tenant->refresh();

        if (! empty($this->metrics['users_limit_newly_exceeded'])) {
            notify()->warning(
                "Alerte : « {$this->tenant->code} » a dépassé son plafond ({$this->tenant->users_count}/{$this->tenant->max_users} utilisateurs)."
            );
        } elseif (! empty($this->metrics['users_limit_exceeded'])) {
            notify()->info(
                "Plafond toujours dépassé : {$this->tenant->users_count}/{$this->tenant->max_users} utilisateurs."
            );
        } else {
            notify()->success('Indicateurs actualisés.');
        }
    }

    public function toggleActive(): void
    {
        $this->tenant->is_active = ! $this->tenant->is_active;
        $this->tenant->save();
        $this->tenant->refresh();

        notify()->success(
            $this->tenant->is_active
                ? "Entreprise « {$this->tenant->code} » réactivée."
                : "Entreprise « {$this->tenant->code} » désactivée — connexion app bloquée."
        );
    }

    public function render()
    {
        $this->tenant->load(['modules' => fn ($q) => $q->orderBy('label')]);
        $subscription = $this->tenant->currentSubscription();
        $subscription?->load('plan');

        return view('livewire.admin.tenant-show', [
            'subscription' => $subscription,
            'enabledModules' => $this->tenant->modules->where('pivot.enabled', true)->values(),
            'typeLabel' => $this->tenant->type_label,
            'loginUrl' => $this->tenant->app_login_url,
        ])->layout('layouts.app', [
            'title' => $this->tenant->name,
            'subtitle' => 'Fiche entreprise · '.$this->tenant->code,
        ]);
    }
}
