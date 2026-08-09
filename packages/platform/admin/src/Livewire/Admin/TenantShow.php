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
        // Always pull live seat count when opening the fiche
        $this->metrics = app(CompanyIntelligenceService::class)->refresh($this->tenant, true);
        $this->tenant->refresh();

        if (! empty($this->metrics['users_limit_newly_exceeded'])) {
            notify()->warning(
                "Plafond utilisateurs dépassé : {$this->tenant->users_count}/{$this->tenant->max_users} sièges actifs pour « {$this->tenant->code} »."
            );
        }
    }

    public function refreshMetrics(): void
    {
        $this->metrics = app(CompanyIntelligenceService::class)->refresh($this->tenant, true);
        $this->tenant->refresh();

        if (! empty($this->metrics['users_limit_exceeded'])) {
            notify()->warning(
                "Plafond dépassé : {$this->tenant->users_count}/{$this->tenant->max_users} utilisateurs actifs."
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
            'limitExceeded' => $this->tenant->isUsersLimitExceeded(),
        ])->layout('layouts.app', [
            'title' => $this->tenant->name,
            'subtitle' => 'Fiche entreprise · '.$this->tenant->code,
        ]);
    }
}
