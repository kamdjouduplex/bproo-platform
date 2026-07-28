<?php

namespace App\Livewire\Tenant;

use App\Services\DashboardService;
use App\Services\ModuleManager;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Pressing\Services\PressingDashboardService;
use Pressing\Support\PressingProfile;

class Dashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantManager::class)->tenant();
        $tenantUser = auth('tenant')->user();
        $moduleLinks = $tenant ? app(ModuleManager::class)->navLinksForTenant($tenant, $tenantUser) : [];
        $moduleKeys = collect($moduleLinks)->pluck('key')->all();

        $currency = $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
        $userName = $tenantUser?->name ?? '';
        $tenantCode = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');
        $monthLabel = now()->translatedFormat('F Y');

        $isPressing = in_array('pressing_orders', $moduleKeys, true)
            || in_array('pressing_deliveries', $moduleKeys, true)
            || ($tenant && $tenant->type === 'pressing');

        if ($isPressing) {
            return $this->renderPressingDashboard($tenantUser, $userName, $tenantCode, $currency, $monthLabel, $moduleLinks);
        }

        return $this->renderRetailDashboard($tenant, $tenantUser, $moduleLinks, $moduleKeys, $currency, $userName, $tenantCode, $monthLabel);
    }

    private function renderPressingDashboard($tenantUser, string $userName, ?string $tenantCode, string $currency, string $monthLabel, array $moduleLinks)
    {
        $profile = PressingProfile::current($tenantUser);
        $service = app(PressingDashboardService::class);
        $metrics = $service->metrics($profile);

        $subtitle = match ($profile) {
            PressingProfile::DRIVER => ($metrics['agence_scope_label'] ?? '')
                .' · '.__('Mes livraisons'),
            PressingProfile::PRODUCTION => ($metrics['agence_scope_label'] ?? '')
                .' · '.__('Atelier / production'),
            default => ($metrics['agence_scope_label'] ?? __('Toutes les agences'))
                .' · '.__('CA du mois').' : '.fmt_money($metrics['revenue_month']).' '.$currency,
        };

        return view('livewire.tenant.dashboard-pressing')
            ->layout('layouts.app', [
                'title' => 'Tableau de bord',
                'subtitle' => $subtitle,
            ])
            ->with([
                'userName' => $userName,
                'tenantCode' => $tenantCode,
                'currency' => $currency,
                'monthLabel' => $monthLabel,
                'pressingMetrics' => $metrics,
                'profile' => $profile,
                'profileLabel' => PressingProfile::label($profile),
                'agenceScopeLabel' => $metrics['agence_scope_label'] ?? null,
                'salesChart' => ($metrics['show_finance'] ?? false) ? $service->revenueLast7Days() : [],
                'recentOrders' => ($metrics['show_full_ops'] ?? false) ? $service->recentOrders() : collect(),
                'overdueOrders' => ($metrics['show_full_ops'] ?? false) ? $service->overdueList() : collect(),
                'waitingDeliveries' => $profile === PressingProfile::DRIVER
                    ? $service->waitingDeliveries(10, true)
                    : ($profile === PressingProfile::RECEPTION || $profile === PressingProfile::ADMIN
                        ? $service->waitingDeliveries(8, false)
                        : collect()),
                'quickActions' => $this->buildPressingQuickActions($moduleLinks, $tenantCode, $profile),
            ]);
    }

    private function renderRetailDashboard($tenant, $tenantUser, array $moduleLinks, array $moduleKeys, string $currency, string $userName, ?string $tenantCode, string $monthLabel)
    {
        $dashboard = app(DashboardService::class);
        $hasInvoicing = in_array('invoicing', $moduleKeys, true);
        $hasSales = in_array('sales', $moduleKeys, true) || $hasInvoicing;
        $hasStock = in_array('stock', $moduleKeys, true);
        $hasReporting = in_array('reporting', $moduleKeys, true);

        $canViewReporting = $tenantUser
            && method_exists($tenantUser, 'hasPermission')
            && $tenantUser->hasPermission('reporting.view');

        $invoiceRevenueMonth = $hasInvoicing ? $dashboard->salesMonth() : 0.0;
        $invoiceCollectedMonth = $hasInvoicing ? $dashboard->invoiceCollectedMonth() : 0.0;
        $subtitle = $hasInvoicing
            ? 'CA facture ' . $monthLabel . ' : ' . fmt_money($invoiceRevenueMonth) . ' ' . $currency
            : 'Vue d\'ensemble de votre activité';

        return view('livewire.tenant.dashboard')
            ->layout('layouts.app', [
                'title' => 'Tableau de bord',
                'subtitle' => $subtitle,
            ])
            ->with([
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
                'invoiceCountMonth' => $hasInvoicing ? $dashboard->salesCountMonth() : 0,
                'expensesMonth' => $canViewReporting ? $dashboard->expensesMonth() : 0.0,
                'recentInvoices' => $hasInvoicing ? $dashboard->recentInvoices(8) : [],
                'storePerformance' => $dashboard->storePerformanceMonth(),
                'storeDimensionReady' => $dashboard->hasStoreDimension(),
                'lowStockItems' => $hasStock ? $dashboard->lowStockItems(6) : [],
                'pendingInvoices' => $hasInvoicing ? $dashboard->pendingInvoicesCount() : 0,
                'unpaidInvoicesTotal' => $hasInvoicing ? $dashboard->unpaidInvoicesTotal() : 0.0,
                'monthLabel' => $monthLabel,
            ]);
    }

    private function buildPressingQuickActions(array $moduleLinks, ?string $tenantCode, string $profile = PressingProfile::RECEPTION): array
    {
        $priority = match ($profile) {
            PressingProfile::DRIVER => [
                'pressing_deliveries' => ['label' => 'Livraisons', 'style' => 'primary'],
            ],
            PressingProfile::PRODUCTION => [
                'pressing_workflow' => ['label' => 'Kanban', 'style' => 'primary'],
                'pressing_fin_production' => ['label' => 'Fin de production', 'style' => 'secondary'],
                'pressing_consumables' => ['label' => 'Bons de sortie', 'style' => 'secondary'],
            ],
            default => [
                'pressing_orders' => ['label' => 'Nouvelle réception', 'style' => 'primary'],
                'pressing_workflow' => ['label' => 'Kanban', 'style' => 'secondary'],
                'pressing_clients' => ['label' => 'Clients', 'style' => 'secondary'],
                'pressing_deliveries' => ['label' => 'Livraisons', 'style' => 'secondary'],
                'pressing_settings' => ['label' => 'Paramétrage', 'style' => 'secondary'],
            ],
        };

        $actions = [];
        $byKey = collect($moduleLinks)->keyBy('key');

        foreach ($priority as $key => $meta) {
            $link = $byKey->get($key);
            if (! $link || ! Route::has($link['route'])) {
                continue;
            }
            $route = $link['route'];
            if ($key === 'pressing_orders' && Route::has('tenant.pressing_orders.create')) {
                $route = 'tenant.pressing_orders.create';
            }
            $actions[] = [
                'label' => $meta['label'],
                'route' => $route,
                'style' => $meta['style'],
            ];
            if (count($actions) >= 5) {
                break;
            }
        }

        return $actions;
    }

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
