<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Analytics extends Component
{
    public function render()
    {
        // ── Platform KPIs ─────────────────────────────────────────────
        $totalTenants       = Tenant::count();
        $activeTenants      = Tenant::where('is_active', true)->count();
        $provisionedTenants = Tenant::where('provisioning_status', 'completed')->count();
        $failedTenants      = Tenant::where('provisioning_status', 'failed')->count();

        // ── Plan distribution ─────────────────────────────────────────
        $planDistribution = Tenant::selectRaw("COALESCE(plan, 'free') as plan, COUNT(*) as count")
            ->groupByRaw("COALESCE(plan, 'free')")
            ->orderByRaw("COUNT(*) DESC")
            ->get()
            ->keyBy('plan');

        // ── Module adoption (top 8 by enabled tenants) ─────────────────
        $moduleAdoption = DB::table('tenant_modules as tm')
            ->join('modules as m', 'm.id', '=', 'tm.module_id')
            ->where('tm.enabled', true)
            ->selectRaw('m.label, m.key, COUNT(*) as tenant_count')
            ->groupBy('m.id', 'm.label', 'm.key')
            ->orderByDesc('tenant_count')
            ->limit(8)
            ->get();

        $maxAdoption = $moduleAdoption->max('tenant_count') ?: 1;

        // ── Tenants created per month (last 6 months) ─────────────────
        $monthlyCreated = Tenant::selectRaw("
                TO_CHAR(created_at, 'YYYY-MM') as month_key,
                TO_CHAR(created_at, 'Mon YYYY') as month_label,
                COUNT(*) as count
            ")
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM'), TO_CHAR(created_at, 'Mon YYYY')")
            ->orderBy('month_key')
            ->get();

        $maxMonthly = $monthlyCreated->max('count') ?: 1;

        // ── Recent tenants ────────────────────────────────────────────
        $recentTenants = Tenant::orderByDesc('created_at')->limit(6)->get();

        return view('livewire.admin.analytics', [
            'totalTenants'       => $totalTenants,
            'activeTenants'      => $activeTenants,
            'provisionedTenants' => $provisionedTenants,
            'failedTenants'      => $failedTenants,
            'planDistribution'   => $planDistribution,
            'moduleAdoption'     => $moduleAdoption,
            'maxAdoption'        => $maxAdoption,
            'monthlyCreated'     => $monthlyCreated,
            'maxMonthly'         => $maxMonthly,
            'recentTenants'      => $recentTenants,
        ])->layout('layouts.app', [
            'title'    => __('Analytique plateforme'),
            'subtitle' => __('Vue d\'ensemble et suivi abonnements'),
        ]);
    }
}
