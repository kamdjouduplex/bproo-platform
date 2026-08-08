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
        ];
    }

    public function refreshMetrics(): void
    {
        $this->metrics = app(CompanyIntelligenceService::class)->refresh($this->tenant, true);
        $this->tenant->refresh();
        notify()->success('Indicateurs actualisés.');
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
            'subtitle' => 'Fiche entreprise · ' . $this->tenant->code,
        ]);
    }
}
