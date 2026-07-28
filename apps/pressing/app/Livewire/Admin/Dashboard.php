<?php

namespace App\Livewire\Admin;

use App\Models\Module;
use App\Models\ModuleEvent;
use App\Models\Tenant;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $tenantsTotal = Tenant::count();
        $tenantsActive = Tenant::where('is_active', true)->count();
        $tenantsProvisioning = Tenant::where('provisioning_status', 'provisioning')->count();
        $tenantsFailed = Tenant::where('provisioning_status', 'failed')->count();
        $modulesCount = Module::count();

        $recentEvents = ModuleEvent::query()
            ->with(['tenant', 'performer'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return view('livewire.admin.dashboard')
            ->layout('layouts.app', [
                'title' => 'Administration',
                'subtitle' => 'Vue d\'ensemble de la plateforme',
            ])
            ->with([
                'tenantsTotal' => $tenantsTotal,
                'tenantsActive' => $tenantsActive,
                'tenantsProvisioning' => $tenantsProvisioning,
                'tenantsFailed' => $tenantsFailed,
                'modulesCount' => $modulesCount,
                'recentEvents' => $recentEvents,
            ]);
    }
}
