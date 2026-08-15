<?php

namespace App\Livewire\Tenant;

use App\Services\DashboardService;
use App\Services\ModuleManager;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantManager::class)->tenant();
        $tenantUser = auth('tenant')->user();
        $dashboard = app(DashboardService::class);
        $moduleLinks = $tenant ? app(ModuleManager::class)->navLinksForTenant($tenant, $tenantUser) : [];
        $moduleKeys = collect($moduleLinks)->pluck('key')->all();

        $isAdmin = $tenantUser
            && method_exists($tenantUser, 'isAdmin')
            && $tenantUser->isAdmin();

        // Uniquement les modules réellement accessibles au profil (pas « table existe »).
        $hasInvoicing = in_array('invoicing', $moduleKeys, true);
        $hasSales = in_array('sales', $moduleKeys, true);
        $hasStock = in_array('stock', $moduleKeys, true);
        $hasBatches = in_array('batches', $moduleKeys, true);
        $hasPrescriptions = in_array('prescriptions', $moduleKeys, true);
        $hasReporting = in_array('reporting', $moduleKeys, true);
        $hasPurchases = in_array('purchases', $moduleKeys, true);
        $hasCaisse = in_array('caisse', $moduleKeys, true);
        $hasPayroll = in_array('payroll', $moduleKeys, true);
        $hasAttendance = in_array('attendance', $moduleKeys, true);

        $canViewFinance = $isAdmin
            || $hasSales
            || $hasCaisse
            || $hasReporting
            || $hasInvoicing;

        $currency = $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
        $userName = $tenantUser?->name ?? '';
        $tenantCode = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');

        $canViewReporting = $tenantUser
            && method_exists($tenantUser, 'hasPermission')
            && $tenantUser->hasPermission('reporting.view');

        $posSalesToday = ($hasSales && $canViewFinance) ? $dashboard->posSalesToday() : 0.0;
        $posBenefitToday = ($hasSales && $canViewFinance) ? $dashboard->posBenefitToday() : 0.0;
        $posTrend = ($hasSales && $canViewFinance) ? $dashboard->posTrendVsYesterday() : null;
        $itemsInStock = $hasStock ? $dashboard->itemsInStockCount() : 0;
        $expiringCount = $hasBatches ? $dashboard->expiringBatchesCount(90) : 0;
        $expiredCount = $hasBatches ? $dashboard->expiredBatchesCount() : 0;
        $activeRx = $hasPrescriptions ? $dashboard->activePrescriptionsCount() : 0;
        $caisse = $hasCaisse ? $dashboard->caisseSnapshot() : null;
        $lowStockItems = $hasStock ? $dashboard->lowStockItems(8) : [];
        $expiringBatches = $hasBatches ? $dashboard->expiringBatches(90, 6) : [];

        $subtitle = ($hasSales && $canViewFinance)
            ? 'Ventes du jour : '.fmt_money($posSalesToday).' '.$currency
            : ($hasPayroll && ! $canViewFinance
                ? 'Votre espace collaborateur'
                : 'Vue d\'ensemble de votre officine');

        return view('livewire.tenant.dashboard')
            ->layout('layouts.app', [
                'title' => 'Tableau de bord',
                'subtitle' => $subtitle,
            ])
            ->with([
                'userName' => $userName,
                'tenantCode' => $tenantCode,
                'currency' => $currency,
                'isAdmin' => $isAdmin,
                'canViewFinance' => $canViewFinance,
                'hasSales' => $hasSales && $canViewFinance,
                'hasStock' => $hasStock,
                'hasBatches' => $hasBatches,
                'hasPrescriptions' => $hasPrescriptions,
                'hasInvoicing' => $hasInvoicing,
                'hasReporting' => $hasReporting,
                'hasPurchases' => $hasPurchases,
                'hasCaisse' => $hasCaisse,
                'hasPayroll' => $hasPayroll,
                'hasAttendance' => $hasAttendance,
                'canViewReporting' => $canViewReporting,
                'moduleLinks' => $moduleLinks,
                'quickActions' => $this->buildQuickActions($moduleLinks, $tenantCode, $hasPrescriptions, $hasBatches, $hasPayroll, $hasAttendance),
                'salesChart' => ($hasSales && $canViewFinance) ? $dashboard->posSalesLast7Days() : [],
                'posSalesToday' => $posSalesToday,
                'posBenefitToday' => $posBenefitToday,
                'posSalesCountToday' => ($hasSales && $canViewFinance) ? $dashboard->posSalesCountToday() : 0,
                'posTrend' => $posTrend,
                'itemsInStock' => $itemsInStock,
                'expiringCount' => $expiringCount,
                'expiredCount' => $expiredCount,
                'activeRx' => $activeRx,
                'caisse' => $caisse,
                'recentSales' => ($hasSales && $canViewFinance) ? $dashboard->recentSales(8) : [],
                'lowStockItems' => $lowStockItems,
                'expiringBatches' => $expiringBatches,
                'invoiceRevenueMonth' => $hasInvoicing ? $dashboard->salesMonth() : 0.0,
                'invoiceCollectedMonth' => $hasInvoicing ? $dashboard->invoiceCollectedMonth() : 0.0,
                'pendingInvoices' => $hasInvoicing ? $dashboard->pendingInvoicesCount() : 0,
                'unpaidInvoicesTotal' => $hasInvoicing ? $dashboard->unpaidInvoicesTotal() : 0.0,
                'monthLabel' => now()->translatedFormat('F Y'),
            ]);
    }

    /**
     * @param  array<int, array{key: string, label: string, route: string, icon?: string}>  $moduleLinks
     * @return array<int, array{label: string, route: string, style: string}>
     */
    private function buildQuickActions(
        array $moduleLinks,
        ?string $tenantCode,
        bool $hasPrescriptions,
        bool $hasBatches,
        bool $hasPayroll,
        bool $hasAttendance
    ): array {
        $priority = [
            'sales' => ['label' => 'Nouvelle vente', 'style' => 'primary'],
            'prescriptions' => ['label' => 'Ordonnances', 'style' => 'secondary'],
            'batches' => ['label' => 'Lots', 'style' => 'secondary'],
            'stock' => ['label' => 'Stock', 'style' => 'secondary'],
            'caisse' => ['label' => 'Caisse', 'style' => 'secondary'],
            'purchases' => ['label' => 'Achats', 'style' => 'secondary'],
            'payroll' => ['label' => 'Mes bulletins', 'style' => 'secondary'],
            'attendance' => ['label' => 'Présence', 'style' => 'secondary'],
        ];

        if (! $hasPrescriptions) {
            unset($priority['prescriptions']);
        }
        if (! $hasBatches) {
            unset($priority['batches']);
        }
        if (! $hasPayroll) {
            unset($priority['payroll']);
        }
        if (! $hasAttendance) {
            unset($priority['attendance']);
        }

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
            } else {
                $actions[] = [
                    'label' => $meta['label'],
                    'route' => $link['route'],
                    'style' => $meta['style'],
                ];
            }

            if (count($actions) >= 5) {
                break;
            }
        }

        return $actions;
    }
}
