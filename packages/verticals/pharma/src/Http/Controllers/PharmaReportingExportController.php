<?php

namespace Pharma\Http\Controllers;

use App\Support\PrintDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Reporting\Exports\ReportingExcelExporter;
use Pharma\Services\PharmaReportingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PharmaReportingExportController
{
    public function excel(Request $request): StreamedResponse
    {
        $this->authorizeExport();
        [$headers, $rows, $title] = $this->dataset($request);
        $filename = 'analyse-ventes-'.now()->format('Y-m-d').'.xls';

        return ReportingExcelExporter::download($filename, $headers, $rows, $title);
    }

    public function print(Request $request): View
    {
        $this->authorizeExport();
        [$headers, $rows, $title] = $this->dataset($request, 400);
        $tenant = app(\App\Services\TenantManager::class)->tenant();
        $settings = app(\App\Services\TenantBrandingService::class)->documentSettings($tenant);

        return view('pharma::print.reporting-sales', array_merge([
            'settings' => $settings,
            'headers' => $headers,
            'rows' => $rows,
            'title' => $title,
            'docNumber' => 'VENTES',
            'numStart' => 2,
            'numEnd' => 5,
        ], PrintDocument::context(
            $request,
            'analyse-ventes',
            now()->format('Ymd'),
            'tenant.pharma-reporting.sales'
        )));
    }

    public function profitabilityExcel(Request $request): StreamedResponse
    {
        $this->authorizeExport();
        [$headers, $rows, $title] = $this->profitabilityDataset($request);
        $filename = 'rentabilite-'.now()->format('Y-m-d').'.xls';

        return ReportingExcelExporter::download($filename, $headers, $rows, $title);
    }

    public function profitabilityPrint(Request $request): View
    {
        $this->authorizeExport();
        [$headers, $rows, $title, $numStart, $numEnd] = $this->profitabilityDataset($request);
        $tenant = app(\App\Services\TenantManager::class)->tenant();
        $settings = app(\App\Services\TenantBrandingService::class)->documentSettings($tenant);

        return view('pharma::print.reporting-sales', array_merge([
            'settings' => $settings,
            'headers' => $headers,
            'rows' => $rows,
            'title' => $title,
            'docNumber' => 'MARGE',
            'numStart' => $numStart,
            'numEnd' => $numEnd,
        ], PrintDocument::context(
            $request,
            'rentabilite',
            now()->format('Ymd'),
            'tenant.pharma-reporting.profitability'
        )));
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|float|int>>, 2: string}
     */
    private function dataset(Request $request, int $limit = 2000): array
    {
        $service = app(PharmaReportingService::class);
        $period = (string) $request->query('period', 'this_month');
        [$from, $to] = $service->resolveRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', '')
        );

        $filters = [
            'category_id' => $request->filled('category') ? (int) $request->query('category') : null,
            'user_id' => $request->filled('seller') ? (int) $request->query('seller') : null,
            'store_id' => $request->filled('store') ? (int) $request->query('store') : null,
            'payment_method' => $request->query('pay') ?: null,
            'search' => (string) $request->query('q', ''),
        ];

        $records = $service->salesListQuery($from, $to, $filters)->limit($limit)->get();
        $headers = ['Date', 'N° vente', 'Produits', 'Qté', 'CA', 'Marge', 'Vendeur', 'Client', 'Statut'];
        $rows = [];
        foreach ($records as $row) {
            $status = $service->saleStatus((float) $row->credit_amount, (float) $row->paid_amount, (float) $row->total);
            $rows[] = [
                Carbon::parse($row->sale_date)->format('d/m/Y'),
                $row->sale_number,
                (int) $row->products_count,
                (float) $row->qty_total,
                (float) $row->total,
                (float) $row->total - (float) $row->cogs,
                $row->seller_name ?: '—',
                $row->client_name ?: 'Comptant',
                $status['label'],
            ];
        }

        return [$headers, $rows, 'Analyse des ventes — '.$service->periodLabel($from, $to)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|float|int>>, 2: string, 3: int, 4: int}
     */
    private function profitabilityDataset(Request $request, int $limit = 2000): array
    {
        $service = app(PharmaReportingService::class);
        $period = (string) $request->query('period', 'this_month');
        [$from, $to] = $service->resolveRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', '')
        );
        $view = $request->query('view') === 'product' ? 'product' : 'category';
        $label = $service->periodLabel($from, $to);

        if ($view === 'product') {
            $headers = ['Produit', 'Catégorie', 'Qté', 'CA', 'Coût', 'Marge', 'Taux'];
            $rows = [];
            foreach ($service->profitabilityByProduct($from, $to, $limit) as $row) {
                $rows[] = [
                    $row['sku'] ? $row['name'].' ('.$row['sku'].')' : $row['name'],
                    $row['category'],
                    $row['qty'],
                    $row['ca'],
                    $row['cogs'],
                    $row['margin'],
                    $row['margin_rate'].' %',
                ];
            }

            return [$headers, $rows, 'Rentabilité par produit — '.$label, 2, 5];
        }

        $headers = ['Catégorie', 'Qté', 'CA', 'Marge', 'Taux'];
        $rows = [];
        foreach ($service->profitabilityByCategory($from, $to) as $row) {
            $rows[] = [
                $row['name'],
                $row['qty'],
                $row['ca'],
                $row['margin'],
                $row['margin_rate'].' %',
            ];
        }

        return [$headers, $rows, 'Rentabilité par catégorie — '.$label, 1, 3];
    }

    public function stockExcel(Request $request): StreamedResponse
    {
        $this->authorizeExport();
        [$headers, $rows, $title] = $this->stockDataset($request);
        $filename = 'stock-'.now()->format('Y-m-d').'.xls';

        return ReportingExcelExporter::download($filename, $headers, $rows, $title);
    }

    public function stockPrint(Request $request): View
    {
        $this->authorizeExport();
        [$headers, $rows, $title, $numStart, $numEnd] = $this->stockDataset($request);
        $tenant = app(\App\Services\TenantManager::class)->tenant();
        $settings = app(\App\Services\TenantBrandingService::class)->documentSettings($tenant);

        return view('pharma::print.reporting-sales', array_merge([
            'settings' => $settings,
            'headers' => $headers,
            'rows' => $rows,
            'title' => $title,
            'docNumber' => 'STOCK',
            'numStart' => $numStart,
            'numEnd' => $numEnd,
        ], PrintDocument::context(
            $request,
            'stock',
            now()->format('Ymd'),
            'tenant.pharma-reporting.stock'
        )));
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|float|int>>, 2: string, 3: int, 4: int}
     */
    private function stockDataset(Request $request, int $limit = 2000): array
    {
        $service = app(PharmaReportingService::class);
        $view = (string) $request->query('view', 'stockouts');
        if (! in_array($view, ['stockouts', 'low', 'expiring', 'dead'], true)) {
            $view = 'stockouts';
        }
        $days = (int) $request->query('days', 90);
        if (! in_array($days, [30, 60, 90], true)) {
            $days = 90;
        }

        if ($view === 'expiring') {
            $headers = ['Lot', 'Produit', 'Expiration', 'Qté', 'Jours'];
            $rows = [];
            foreach ($service->expiringBatches($days, $limit) as $row) {
                $rows[] = [
                    $row['batch_number'],
                    $row['item_name'],
                    $row['expiry_date'],
                    $row['quantity'],
                    $row['days'],
                ];
            }

            return [$headers, $rows, 'Lots bientôt périmés (< '.$days.' j.)', 3, 4];
        }

        if ($view === 'dead') {
            $headers = ['Produit', 'Dispo', 'Dernière vente'];
            $rows = [];
            foreach ($service->deadStockItems($days, $limit) as $row) {
                $rows[] = [
                    $row['sku'] ? $row['name'].' ('.$row['sku'].')' : $row['name'],
                    $row['available'],
                    $row['last_sale'] ?: 'Jamais',
                ];
            }

            return [$headers, $rows, 'Stock dormant ('.$days.' j. sans vente)', 1, 1];
        }

        $headers = ['Produit', 'Dispo', 'Seuil'];
        $source = $view === 'low' ? $service->lowStockItems($limit) : $service->stockoutItems($limit);
        $rows = [];
        foreach ($source as $row) {
            $rows[] = [
                $row['sku'] ? $row['name'].' ('.$row['sku'].')' : $row['name'],
                $row['available'],
                $row['reorder_point'] ?? '—',
            ];
        }
        $title = $view === 'low' ? 'Stock bas' : 'Ruptures';

        return [$headers, $rows, $title, 1, 2];
    }

    private function authorizeExport(): void
    {
        $user = auth('tenant')->user();
        if (! $user) {
            abort(403);
        }
        $ok = (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'hasPermission') && (
                $user->hasPermission('reporting.export')
                || $user->hasPermission('reporting.view')
            ));
        if (! $ok) {
            abort(403);
        }
    }
}
