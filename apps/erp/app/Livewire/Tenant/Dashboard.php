<?php

namespace App\Livewire\Tenant;

use App\Services\DashboardService;
use App\Services\ModuleManager;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Dashboard extends Component
{
    /** @var array<string, mixed>|null */
    private ?array $dashboardViewData = null;

    /**
     * Livewire 4 injects this into the Blade view on every render.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return $this->dashboardViewData ??= $this->buildDashboardData();
    }

    public function render()
    {
        $data = $this->with();

        return view('livewire.tenant.dashboard')
            ->layout('layouts.app', [
                'title' => 'Tableau de bord',
                'subtitle' => $data['layoutSubtitle'],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $tenantUser = auth('tenant')->user();
        $dashboard = app(DashboardService::class);
        $moduleLinks = $tenant ? app(ModuleManager::class)->navLinksForTenant($tenant, $tenantUser) : [];
        $moduleKeys = collect($moduleLinks)->pluck('key')->all();

        $hasInvoicing = in_array('invoicing', $moduleKeys, true);
        $hasSales = in_array('sales', $moduleKeys, true) || $hasInvoicing;
        $hasStock = in_array('stock', $moduleKeys, true);
        $hasReporting = in_array('reporting', $moduleKeys, true);

        $currency = $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
        $userName = $tenantUser?->name ?? '';
        $tenantCode = (string) ($tenant?->code ?? request()->query('tenant') ?? session('tenant_code') ?? '');

        $canViewReporting = (bool) ($tenantUser
            && method_exists($tenantUser, 'hasPermission')
            && $tenantUser->hasPermission('reporting.view'));

        $monthLabel = now()->translatedFormat('F Y');
        $invoiceRevenueMonth = $hasInvoicing ? (float) $dashboard->salesMonth() : 0.0;
        $invoiceCollectedMonth = $hasInvoicing ? (float) $dashboard->invoiceCollectedMonth() : 0.0;
        $invoiceCountMonth = $hasInvoicing ? (int) $dashboard->salesCountMonth() : 0;

        return [
            'userName' => $userName,
            'tenantCode' => $tenantCode,
            'currency' => $currency,
            'hasSales' => $hasSales,
            'hasStock' => $hasStock,
            'hasInvoicing' => $hasInvoicing,
            'hasReporting' => $hasReporting,
            'canViewReporting' => $canViewReporting,
            'moduleLinks' => $moduleLinks,
            'quickActions' => $this->buildQuickActions($moduleLinks, $tenantCode),
            'salesChart' => $hasSales ? $dashboard->salesLast7Days() : [],
            'invoiceRevenueMonth' => $invoiceRevenueMonth,
            'invoiceCollectedMonth' => $invoiceCollectedMonth,
            'invoiceCountMonth' => $invoiceCountMonth,
            'expensesMonth' => $canViewReporting ? (float) $dashboard->expensesMonth() : 0.0,
            'recentInvoices' => $hasInvoicing ? $dashboard->recentInvoices(8) : [],
            'storePerformance' => $dashboard->storePerformanceMonth(),
            'storeDimensionReady' => $dashboard->hasStoreDimension(),
            'lowStockItems' => $hasStock ? $dashboard->lowStockItems(6) : [],
            'pendingInvoices' => $hasInvoicing ? (int) $dashboard->pendingInvoicesCount() : 0,
            'unpaidInvoicesTotal' => $hasInvoicing ? (float) $dashboard->unpaidInvoicesTotal() : 0.0,
            'monthLabel' => $monthLabel,
            'layoutSubtitle' => $hasInvoicing
                ? 'CA facture '.$monthLabel.' : '.fmt_money($invoiceRevenueMonth).' '.$currency
                : 'Vue d\'ensemble de votre activité',
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, route: string, icon?: string}>  $moduleLinks
     * @return array<int, array{label: string, route: string, style: string}>
     */
    private function buildQuickActions(array $moduleLinks, ?string $tenantCode): array
    {
        $priority = [
            'sales' => ['label' => 'Nouvelle vente', 'style' => 'primary'],
            'stock' => ['label' => 'Stock', 'style' => 'secondary'],
            'caisse' => ['label' => 'Caisse', 'style' => 'secondary'],
            'invoicing' => ['label' => 'Facturation', 'style' => 'secondary'],
            'reporting' => ['label' => 'Rapports', 'style' => 'secondary'],
            'clients' => ['label' => 'Clients', 'style' => 'secondary'],
        ];

        $actions = [];
        $byKey = collect($moduleLinks)->keyBy('key');

        foreach ($priority as $key => $meta) {
            $link = $byKey->get($key);
            if (! $link || ! Route::has($link['route'])) {
                continue;
            }

            if ($key === 'sales' && Route::has('tenant.sales.create')) {
                $actions[] = [
                    'label' => $meta['label'],
                    'route' => 'tenant.sales.create',
                    'style' => $meta['style'],
                ];
                continue;
            }

            $actions[] = [
                'label' => $key === 'sales' ? $meta['label'] : ($meta['label'] ?? $link['label']),
                'route' => $link['route'],
                'style' => $meta['style'],
            ];

            if (count($actions) >= 5) {
                break;
            }
        }

        return $actions;
    }
}
