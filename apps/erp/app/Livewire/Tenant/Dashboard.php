<?php

namespace App\Livewire\Tenant;

use App\Services\DashboardOverviewService;
use App\Services\ModuleManager;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class Dashboard extends Component
{
    public string $month = '';

    /** @var array<string, mixed>|null */
    private ?array $dashboardViewData = null;

    private ?string $dashboardViewMonth = null;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updatedMonth(mixed $value): void
    {
        $month = is_string($value) ? $value : (string) $this->month;
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->month = now()->format('Y-m');
        } else {
            $parsed = Carbon::createFromFormat('Y-m', $month);
            if (! $parsed) {
                $this->month = now()->format('Y-m');
            } else {
                $parsed = $parsed->startOfMonth();
                $min = now()->copy()->subYears(3)->startOfMonth();
                $max = now()->copy()->endOfMonth();
                $this->month = ($parsed->lt($min) || $parsed->gt($max))
                    ? now()->format('Y-m')
                    : $month;
            }
        }

        $this->dashboardViewData = null;
        $this->dashboardViewMonth = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        if ($this->dashboardViewData !== null && $this->dashboardViewMonth === $this->month) {
            return $this->dashboardViewData;
        }

        $this->dashboardViewMonth = $this->month;
        $this->dashboardViewData = $this->buildDashboardData();

        return $this->dashboardViewData;
    }

    public function render()
    {
        return view('livewire.tenant.dashboard', $this->with())
            ->layout('layouts.app', [
                'title' => 'Tableau de bord',
                'subtitle' => 'Vue d’ensemble de votre activité',
                'hidePageHeader' => true,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $tenantUser = auth('tenant')->user();
        $moduleLinks = $tenant ? app(ModuleManager::class)->navLinksForTenant($tenant, $tenantUser) : [];
        $moduleKeys = collect($moduleLinks)->pluck('key')->all();

        $currency = $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
        $tenantCode = (string) ($tenant?->code ?? request()->query('tenant') ?? session('tenant_code') ?? '');
        $hasInvoicing = in_array('invoicing', $moduleKeys, true);

        return [
            'tenantCode' => $tenantCode,
            'currency' => $currency,
            'hasInvoicing' => $hasInvoicing,
            'overview' => app(DashboardOverviewService::class)->snapshot($this->monthStart()),
            'quickActions' => $this->buildQuickActions(),
        ];
    }

    private function monthStart(): Carbon
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : now()->format('Y-m');

        return Carbon::parse($month.'-01')->startOfMonth();
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string}>
     */
    private function buildQuickActions(): array
    {
        $catalog = [
            ['label' => 'Nouvelle vente', 'route' => 'tenant.sales.create', 'icon' => 'shopping-bag'],
            ['label' => 'Devis', 'route' => 'tenant.quotations.create', 'icon' => 'document'],
            ['label' => 'Facture', 'route' => 'tenant.invoicing.create', 'icon' => 'receipt'],
            ['label' => 'Paiement', 'route' => 'tenant.invoice_payments.index', 'icon' => 'credit-card'],
            ['label' => 'Nouvel achat', 'route' => 'tenant.purchases.create', 'icon' => 'package'],
            ['label' => 'Dépense', 'route' => 'tenant.expenses.create', 'icon' => 'banknotes'],
            ['label' => 'Transfert caisse', 'route' => 'tenant.caisse.index', 'icon' => 'wallet'],
            ['label' => 'Rapport journalier', 'route' => 'tenant.sales.daily-report', 'fallback' => 'tenant.reporting.index', 'icon' => 'chart'],
        ];

        $actions = [];
        foreach ($catalog as $item) {
            $route = $item['route'];
            if (! Route::has($route) && ! empty($item['fallback']) && Route::has($item['fallback'])) {
                $route = $item['fallback'];
            }
            if (! Route::has($route)) {
                continue;
            }

            $actions[] = [
                'label' => $item['label'],
                'route' => $route,
                'icon' => $item['icon'],
            ];
        }

        return $actions;
    }
}
