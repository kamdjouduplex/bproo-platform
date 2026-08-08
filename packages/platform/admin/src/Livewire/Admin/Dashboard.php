<?php

namespace App\Livewire\Admin;

use App\Models\Module;
use App\Models\ModuleEvent;
use App\Models\PlatformProspect;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantPayment;
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

        $subsActive = Subscription::where('status', Subscription::STATUS_ACTIVE)->count();
        $subsSuspended = Subscription::where('status', Subscription::STATUS_SUSPENDED)->count();
        $paymentsMonth = (float) TenantPayment::where('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount');

        $prospectsOpen = PlatformProspect::whereNull('converted_tenant_id')
            ->whereNotIn('stage', [PlatformProspect::STAGE_LOST, PlatformProspect::STAGE_WON])
            ->count();
        $followUpsDue = PlatformProspect::whereNull('converted_tenant_id')
            ->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', '<=', now()->toDateString())
            ->count();

        $byApp = Tenant::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $recentEvents = ModuleEvent::query()
            ->with(['tenant', 'performer'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentProspects = PlatformProspect::query()
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('livewire.admin.dashboard')
            ->layout('layouts.app', [
                'title' => 'Control Center',
                'subtitle' => 'Cerveau ops de la plateforme',
            ])
            ->with([
                'tenantsTotal' => $tenantsTotal,
                'tenantsActive' => $tenantsActive,
                'tenantsProvisioning' => $tenantsProvisioning,
                'tenantsFailed' => $tenantsFailed,
                'modulesCount' => $modulesCount,
                'subsActive' => $subsActive,
                'subsSuspended' => $subsSuspended,
                'paymentsMonth' => $paymentsMonth,
                'prospectsOpen' => $prospectsOpen,
                'followUpsDue' => $followUpsDue,
                'byApp' => $byApp,
                'productTypes' => config('tenant_types.types', []),
                'recentEvents' => $recentEvents,
                'recentProspects' => $recentProspects,
            ]);
    }
}
