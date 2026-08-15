<?php

namespace InovCom\Reporting\Http\Livewire;

use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Reporting\Exports\ReportingExcelExporter;
use InovCom\Reporting\Services\ReportingService;
use Livewire\Component;

class ReportingIndex extends Component
{
    public string $period = 'monthly';

    public ?string $periodValue = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** general | finances | commercial | ventes | clients | explorer */
    public ?string $activeTab = 'general';

    /** Explorer dynamique */
    public string $reportType = 'ca_client';

    public ?int $filterClientId = null;

    public string $filterClientSearch = '';

    public int $reportLimit = 100;

    public function mount(): void
    {
        $this->syncPeriodValue();
        if ($this->period === 'custom') {
            $this->dateFrom = $this->dateFrom ?? now()->startOfMonth()->format('Y-m-d');
            $this->dateTo = $this->dateTo ?? now()->format('Y-m-d');
        }
    }

    public function updatedPeriod(): void
    {
        $this->syncPeriodValue();
        if ($this->period === 'custom' && ! $this->dateFrom) {
            $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
            $this->dateTo = now()->format('Y-m-d');
        }
    }

    public function applyPeriod(): void
    {
        // Trigger re-render
    }

    public function selectTab(string $tab): void
    {
        $allowed = ['general', 'finances', 'commercial', 'ventes', 'clients', 'explorer'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    public function openExplorer(string $reportType = 'ca_client'): void
    {
        $this->reportType = $reportType;
        $this->activeTab = 'explorer';
    }

    public function openExplorerForClient(int $clientId, string $reportType = 'ca_client'): void
    {
        $this->filterClientId = $clientId > 0 ? $clientId : null;
        $this->openExplorer($reportType);
    }

    public function selectClient(int $clientId): void
    {
        $this->filterClientId = $clientId > 0 ? $clientId : null;
    }

    public function clearClientFilter(): void
    {
        $this->filterClientId = null;
        $this->filterClientSearch = '';
    }

    public function exportExplorerExcel()
    {
        $user = auth('tenant')->user();
        if ($user && method_exists($user, 'hasPermission') && ! $user->hasPermission('reporting.export') && ! $user->hasPermission('reporting.view')) {
            session()->flash('error', 'Permission d\'export insuffisante.');

            return null;
        }

        $reporting = app(ReportingService::class);
        [$start, $end] = $reporting->getDateRange($this->period, $this->resolvePeriodValue());
        $report = $reporting->getExplorerReport(
            $this->reportType,
            $start,
            $end,
            $this->filterClientId,
            $this->reportLimit
        );

        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $this->reportType) ?: 'rapport';
        $filename = 'reporting-' . $slug . '-' . now()->format('Y-m-d') . '.xls';

        return ReportingExcelExporter::download(
            $filename,
            $report['headers'],
            $report['rows'],
            ($report['title'] ?? 'Rapport') . ' — ' . $this->getPeriodLabel($start, $end)
        );
    }

    public function explorerPdfUrl(): string
    {
        return $this->explorerExportUrl('tenant.reporting.explorer.pdf');
    }

    public function explorerPrintUrl(): string
    {
        return $this->explorerExportUrl('tenant.reporting.explorer.print');
    }

    private function explorerExportUrl(string $routeName): string
    {
        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        $params = [
            'tenant' => $tenantCode,
            'report_type' => $this->reportType,
            'period' => $this->period,
            'limit' => $this->reportLimit,
        ];

        if ($this->periodValue) {
            $params['period_value'] = $this->periodValue;
        }
        if ($this->period === 'custom') {
            $params['date_from'] = $this->dateFrom;
            $params['date_to'] = $this->dateTo;
        }
        if ($this->filterClientId) {
            $params['client_id'] = $this->filterClientId;
        }

        return route($routeName, $params);
    }

    public function render()
    {
        $reporting = app(ReportingService::class);
        $periodValue = $this->resolvePeriodValue();
        [$start, $end] = $reporting->getDateRange($this->period, $periodValue);
        [$prevStart, $prevEnd] = $reporting->getPreviousDateRange($this->period, $start, $end);

        $financial = $reporting->getFinancialOverview($start, $end);
        $financialPrev = $reporting->getFinancialOverview($prevStart, $prevEnd);

        $quotationsSummary = $reporting->getQuotationsSummary($start, $end);
        $invoicesSummary = $reporting->getInvoicesSummary($start, $end);
        $invoicePaymentsByMethod = $reporting->getInvoicePaymentsByMethod($start, $end);
        $deliveriesSummary = $reporting->getDeliveriesSummary($start, $end);
        $outstandingInvoices = $reporting->getTopOutstandingInvoices(8);

        $salesTotal = (float) $financial['pos_sales'];
        $expensesTotal = (float) $financial['expenses'];
        $lossesTotal = (float) $financial['losses'];
        $benefit = (float) $financial['benefit'];
        $payrollTotal = (float) $financial['payroll'];
        $purchasesTotal = (float) $financial['purchases'];
        $debtsSummary = $reporting->getDebtsSummary($start, $end);
        $lowStock = $reporting->getLowStockItems();
        $outOfStock = $reporting->getOutOfStockItems();
        $storePerformance = $reporting->getStorePerformance($start, $end);
        $storeDimensionReady = $reporting->hasStoreDimension();

        $marginPct = $salesTotal > 0
            ? (($salesTotal - (float) $financial['cogs']) / $salesTotal) * 100
            : 0.0;

        $healthIndicators = $this->buildHealthIndicators(
            $benefit,
            $marginPct,
            (int) ($debtsSummary['overdue_count'] ?? 0),
            (float) ($debtsSummary['overdue_total'] ?? 0),
            count($outOfStock),
            (float) ($invoicesSummary['outstanding_balance'] ?? 0)
        );

        $kpiTrends = [
            'pos_sales' => $reporting->percentChange($salesTotal, (float) $financialPrev['pos_sales']),
            'benefit' => $reporting->percentChange($benefit, (float) $financialPrev['benefit']),
            'invoices_issued' => $reporting->percentChange(
                (float) $financial['invoices_issued_total'],
                (float) $financialPrev['invoices_issued_total']
            ),
            'encaissements' => $reporting->percentChange(
                (float) $financial['encaissements_total'],
                (float) $financialPrev['encaissements_total']
            ),
            'tva_total' => $reporting->percentChange(
                (float) ($financial['tva_total'] ?? 0),
                (float) ($financialPrev['tva_total'] ?? 0)
            ),
        ];

        $topByRevenue = [];
        $topByQuantity = [];
        $salesByTier = [];
        $salesCount = 0;
        $avgSale = 0.0;
        $distinctClients = 0;
        $expensesByCategory = [];
        $lossesByReason = [];
        $topClients = [];
        $clientPerformance = [];
        $clientPerformanceTotals = [
            'invoice_revenue' => 0.0,
            'pos_revenue' => 0.0,
            'total_revenue' => 0.0,
            'quotation_total' => 0.0,
            'quotation_count' => 0,
            'client_count' => 0,
        ];
        $topSales = [];
        $explorerReport = null;
        $clientSuggestions = [];

        if (in_array($this->activeTab, ['ventes', 'general', 'finances'], true)) {
            $salesCount = $reporting->getSalesCount($start, $end);
            $avgSale = $reporting->getAverageSaleAmount($start, $end);
            $distinctClients = $reporting->getDistinctClientsCount($start, $end);
            $topSales = $reporting->getTopSalesByTotal($start, $end, 10);
        }
        if (in_array($this->activeTab, ['ventes', 'general'], true)) {
            $topByRevenue = $reporting->getTopSellingProducts($start, $end, 10);
            $topByQuantity = $reporting->getTopSellingByQuantity($start, $end, 10);
        }
        if (in_array($this->activeTab, ['finances', 'general'], true)) {
            $expensesByCategory = $reporting->getExpensesByCategory($start, $end);
            $lossesByReason = $reporting->getLossesByReason($start, $end);
        }
        if (in_array($this->activeTab, ['clients', 'general'], true)) {
            $clientPerformance = $reporting->getClientPerformanceReport($start, $end);
            $clientPerformanceTotals = [
                'invoice_revenue' => array_sum(array_column($clientPerformance, 'invoice_revenue')),
                'pos_revenue' => array_sum(array_column($clientPerformance, 'pos_revenue')),
                'total_revenue' => array_sum(array_column($clientPerformance, 'total_revenue')),
                'quotation_total' => array_sum(array_column($clientPerformance, 'quotation_total')),
                'quotation_count' => (int) array_sum(array_column($clientPerformance, 'quotation_count')),
                'client_count' => count($clientPerformance),
            ];
            $topClients = array_map(fn (array $row) => [
                'client_name' => $row['client_name'],
                'revenue' => $row['total_revenue'],
                'sale_count' => $row['pos_sale_count'] + $row['invoice_count'],
            ], array_slice($clientPerformance, 0, 10));
        }

        if ($this->activeTab === 'explorer') {
            $explorerReport = $reporting->getExplorerReport(
                $this->reportType,
                $start,
                $end,
                $this->filterClientId,
                $this->reportLimit
            );
            $clientSuggestions = $this->searchClients($this->filterClientSearch);
            if ($this->filterClientId && Schema::connection('tenant')->hasTable('clients')) {
                $selected = DB::connection('tenant')->table('clients')->where('id', $this->filterClientId)->value('name');
                if ($selected) {
                    $clientSuggestions = [['id' => $this->filterClientId, 'name' => (string) $selected]];
                }
            }
        }

        $periodLabel = $this->getPeriodLabel($start, $end);
        $tenant = app(TenantManager::class)->tenant();
        $currency = $tenant ? (string) $tenant->getSetting('currency', 'XOF') : 'XOF';
        $tenantCode = $tenant?->code ?? request()->query('tenant') ?? session('tenant_code');

        $summaryCards = $this->buildSummaryCards($financial, $healthIndicators, $currency);

        $daysInRange = $start->diffInDays($end) + 1;
        $chartDaily = [];
        if ($daysInRange <= 31) {
            $dailySales = $reporting->getDailySalesTrend($start, $end);
            $dailyCogs = $reporting->getDailyCogsTrend($start, $end);
            $dailyLosses = $reporting->getDailyLossesTrend($start, $end);
            foreach ($dailySales as $date => $sales) {
                $cogs = $dailyCogs[$date] ?? 0;
                $losses = $dailyLosses[$date] ?? 0;
                $chartDaily[] = [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('d/m'),
                    'sales' => round($sales, 0),
                    'benefit' => round($sales - $cogs - $losses, 0),
                ];
            }
        }

        return view('inovcom-reporting::livewire.reporting.index')
            ->layout('layouts.app', [
                'title' => 'Rapports et analyses',
                'subtitle' => $periodLabel,
            ])
            ->with([
                'start' => $start,
                'end' => $end,
                'periodLabel' => $periodLabel,
                'currency' => $currency,
                'tenantCode' => $tenantCode,
                'summaryCards' => $summaryCards,
                'chartDaily' => $chartDaily,
                'financial' => $financial,
                'kpiTrends' => $kpiTrends,
                'quotationsSummary' => $quotationsSummary,
                'invoicesSummary' => $invoicesSummary,
                'invoicePaymentsByMethod' => $invoicePaymentsByMethod,
                'deliveriesSummary' => $deliveriesSummary,
                'outstandingInvoices' => $outstandingInvoices,
                'salesTotal' => $salesTotal,
                'salesCount' => $salesCount,
                'avgSale' => $avgSale,
                'expensesTotal' => $expensesTotal,
                'lossesTotal' => $lossesTotal,
                'benefit' => $benefit,
                'payrollTotal' => $payrollTotal,
                'purchasesTotal' => $purchasesTotal,
                'debtsSummary' => $debtsSummary,
                'healthIndicators' => $healthIndicators,
                'topByRevenue' => $topByRevenue,
                'topByQuantity' => $topByQuantity,
                'salesByTier' => $salesByTier,
                'topSales' => $topSales,
                'expensesByCategory' => $expensesByCategory,
                'lossesByReason' => $lossesByReason,
                'topClients' => $topClients,
                'clientPerformance' => $clientPerformance,
                'clientPerformanceTotals' => $clientPerformanceTotals,
                'distinctClients' => $distinctClients,
                'lowStock' => $lowStock,
                'outOfStock' => $outOfStock,
                'storePerformance' => $storePerformance,
                'storeDimensionReady' => $storeDimensionReady,
                'marginPct' => $marginPct,
                'hasQuotations' => $reporting->hasQuotationsModule(),
                'hasInvoicing' => $reporting->hasInvoicingModule(),
                'hasInvoicePayments' => $reporting->hasInvoicePaymentsModule(),
                'explorerReport' => $explorerReport,
                'clientSuggestions' => $clientSuggestions,
                'reportTypes' => $this->reportTypeOptions($reporting),
            ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function searchClients(string $search): array
    {
        if (! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $like = '%' . $search . '%';

        return DB::connection('tenant')
            ->table('clients')
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function reportTypeOptions(ReportingService $reporting): array
    {
        $types = [
            'ca_client' => 'CA par client / entreprise',
            'top_products' => 'Top marchandises (par CA)',
            'top_products_qty' => 'Top marchandises (par quantité)',
            'ventes_direct' => 'Détail ventes directes',
            'stock_low' => 'Stock faible',
            'stock_out' => 'Ruptures de stock',
        ];
        if ($reporting->hasInvoicingModule()) {
            $types['factures'] = 'Détail factures (CA Facture)';
            $types['ca_ht_tva'] = 'CA HT / TVA';
        }

        return $types;
    }

    private function resolvePeriodValue(): ?string
    {
        if ($this->period === 'custom' && $this->dateFrom && $this->dateTo) {
            return $this->dateFrom . '|' . $this->dateTo;
        }

        return $this->periodValue;
    }

    /**
     * @return array<int, array{label: string, value: string, status: string|null, trend: float|null, icon: string, tone: string}>
     */
    private function buildSummaryCards(array $financial, array $healthIndicators, string $currency): array
    {
        $cards = [];
        $cards[] = [
            'label' => 'CA Vente Direct',
            'value' => fmt_money($financial['pos_sales']) . ' ' . $currency,
            'status' => null,
            'trend' => null,
            'icon' => 'shopping-bag',
            'tone' => 'teal',
        ];
        if ($financial['invoices_issued_total'] > 0) {
            $cards[] = [
                'label' => 'CA Facture',
                'value' => fmt_money($financial['invoices_issued_total']) . ' ' . $currency,
                'status' => null,
                'trend' => null,
                'icon' => 'document',
                'tone' => 'blue',
            ];
        }
        if (($financial['ca_ht'] ?? 0) > 0) {
            $cards[] = [
                'label' => 'CA HT (factures)',
                'value' => fmt_money($financial['ca_ht']) . ' ' . $currency,
                'status' => null,
                'trend' => null,
                'icon' => 'document',
                'tone' => 'violet',
            ];
        }
        if (($financial['tva_total'] ?? 0) > 0) {
            $cards[] = [
                'label' => 'TVA période',
                'value' => fmt_money($financial['tva_total']) . ' ' . $currency,
                'status' => null,
                'trend' => null,
                'icon' => 'banknotes',
                'tone' => 'amber',
            ];
        }
        if ($financial['encaissements_total'] > 0) {
            $cards[] = [
                'label' => 'Encaissements',
                'value' => fmt_money($financial['encaissements_total']) . ' ' . $currency,
                'status' => 'good',
                'trend' => null,
                'icon' => 'wallet',
                'tone' => 'green',
            ];
        }
        $cards[] = [
            'label' => 'Dépenses',
            'value' => fmt_money($financial['expenses']) . ' ' . $currency,
            'status' => null,
            'trend' => null,
            'icon' => 'shopping-bag',
            'tone' => 'rose',
        ];
        $cards[] = [
            'label' => 'Pertes',
            'value' => fmt_money($financial['losses']) . ' ' . $currency,
            'status' => null,
            'trend' => null,
            'icon' => 'alert',
            'tone' => 'rose',
        ];
        if ($financial['payroll'] != 0) {
            $cards[] = [
                'label' => 'Paie',
                'value' => fmt_money($financial['payroll']) . ' ' . $currency,
                'status' => null,
                'trend' => null,
                'icon' => 'banknotes',
                'tone' => 'violet',
            ];
        }
        if ($financial['purchases'] != 0) {
            $cards[] = [
                'label' => 'Achats',
                'value' => fmt_money($financial['purchases']) . ' ' . $currency,
                'status' => null,
                'trend' => null,
                'icon' => 'package',
                'tone' => 'amber',
            ];
        }
        if ($financial['outstanding_invoices'] > 0) {
            $cards[] = [
                'label' => 'Factures impayées',
                'value' => fmt_money($financial['outstanding_invoices']) . ' ' . $currency,
                'status' => 'warn',
                'trend' => null,
                'icon' => 'receipt',
                'tone' => 'amber',
            ];
        }
        $cards[] = [
            'label' => 'Bénéfice brut',
            'value' => fmt_money($financial['benefit']) . ' ' . $currency,
            'status' => $financial['benefit'] >= 0 ? 'good' : 'danger',
            'trend' => null,
            'icon' => 'chart',
            'tone' => $financial['benefit'] >= 0 ? 'green' : 'rose',
        ];

        return $cards;
    }

    /**
     * @return array<string, array{label: string, value: string|float, status: string, desc: string}>
     */
    private function buildHealthIndicators(
        float $benefit,
        float $marginPct,
        int $overdueCount,
        float $overdueTotal,
        int $outOfStockCount,
        float $outstandingInvoices
    ): array {
        $indicators = [];

        $indicators['benefit'] = [
            'label' => 'Bénéfice',
            'value' => $benefit,
            'status' => $benefit >= 0 ? 'good' : 'danger',
            'desc' => $benefit >= 0 ? 'Positif' : 'Négatif',
        ];

        $indicators['margin'] = [
            'label' => 'Marge brute Vente Direct',
            'value' => round($marginPct, 1) . ' %',
            'status' => $marginPct >= 20 ? 'good' : ($marginPct >= 10 ? 'warn' : 'danger'),
            'desc' => $marginPct >= 20 ? 'Bonne' : ($marginPct >= 10 ? 'Moyenne' : 'Faible'),
        ];

        $indicators['debts'] = [
            'label' => 'Créances en retard',
            'value' => $overdueCount,
            'status' => $overdueCount === 0 ? 'good' : 'danger',
            'desc' => $overdueCount === 0 ? 'Aucune' : $overdueCount . ' créance(s)',
        ];

        $indicators['invoices'] = [
            'label' => 'Solde factures ouvertes',
            'value' => $outstandingInvoices,
            'status' => $outstandingInvoices <= 0.01 ? 'good' : ($outstandingInvoices < 500000 ? 'warn' : 'danger'),
            'desc' => $outstandingInvoices <= 0.01 ? 'Aucun' : 'À recouvrer',
        ];

        $indicators['stock'] = [
            'label' => 'Ruptures',
            'value' => $outOfStockCount,
            'status' => $outOfStockCount === 0 ? 'good' : ($outOfStockCount <= 3 ? 'warn' : 'danger'),
            'desc' => $outOfStockCount === 0 ? 'Aucune' : $outOfStockCount . ' article(s)',
        ];

        return $indicators;
    }

    private function syncPeriodValue(): void
    {
        $this->periodValue = match ($this->period) {
            'daily' => now()->format('Y-m-d'),
            'weekly' => now()->format('o-\WW'),
            'monthly' => now()->format('Y-m'),
            'yearly' => (string) now()->year,
            'custom' => null,
            default => null,
        };
    }

    private function getPeriodLabel(Carbon $start, Carbon $end): string
    {
        return match ($this->period) {
            'daily' => $start->translatedFormat('l d F Y'),
            'weekly' => 'Semaine du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y'),
            'monthly' => $start->translatedFormat('F Y'),
            'yearly' => (string) $start->year,
            'custom' => $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y'),
            default => $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y'),
        };
    }
}
