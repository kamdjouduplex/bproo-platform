<?php

namespace InovCom\Reporting\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Reporting\Services\ReportingService;
use Symfony\Component\HttpFoundation\Response;

class ReportingExplorerExportController
{
    public function pdf(Request $request): Response
    {
        [$report, $periodLabel, $slug] = $this->resolveReport($request);

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $filename = 'reporting-'.$slug.'-'.now()->format('Y-m-d').'.pdf';

        $pdf = Pdf::loadView('inovcom-reporting::print.explorer-report', [
            'title' => (string) ($report['title'] ?? 'Rapport'),
            'periodLabel' => $periodLabel,
            'headers' => $report['headers'] ?? [],
            'rows' => $report['rows'] ?? [],
            'meta' => $report['meta'] ?? [],
            'shopName' => $settings['shop_name'] ?? ($tenant?->name),
            'generatedAt' => now(),
            'forPdf' => true,
            'autoPrint' => false,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function print(Request $request): View
    {
        [$report, $periodLabel, $slug] = $this->resolveReport($request);

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $printContext = PrintDocument::context(
            $request,
            'rapport-'.$slug,
            now()->format('Y-m-d'),
            'tenant.reporting.index'
        );

        return view('inovcom-reporting::print.explorer-report', array_merge([
            'title' => (string) ($report['title'] ?? 'Rapport'),
            'periodLabel' => $periodLabel,
            'headers' => $report['headers'] ?? [],
            'rows' => $report['rows'] ?? [],
            'meta' => $report['meta'] ?? [],
            'shopName' => $settings['shop_name'] ?? ($tenant?->name),
            'generatedAt' => now(),
            'forPdf' => false,
            'autoPrint' => true,
            'printPageSize' => 'A4 landscape',
        ], $printContext));
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function resolveReport(Request $request): array
    {
        $user = auth('tenant')->user();
        if ($user && method_exists($user, 'hasPermission')
            && ! $user->hasPermission('reporting.export')
            && ! $user->hasPermission('reporting.view')) {
            abort(403, 'Permission d\'export insuffisante.');
        }

        $reportType = (string) $request->query('report_type', 'stock_low');
        $period = (string) $request->query('period', 'monthly');
        $periodValue = $request->query('period_value');
        $periodValue = is_string($periodValue) && $periodValue !== '' ? $periodValue : null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $clientId = $request->query('client_id');
        $clientId = is_numeric($clientId) ? (int) $clientId : null;
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min(500, $limit));

        $reporting = app(ReportingService::class);

        if ($period === 'custom' && is_string($dateFrom) && is_string($dateTo)) {
            $start = \Carbon\Carbon::parse($dateFrom)->startOfDay();
            $end = \Carbon\Carbon::parse($dateTo)->endOfDay();
        } else {
            [$start, $end] = $reporting->getDateRange($period, $periodValue);
        }

        $report = $reporting->getExplorerReport($reportType, $start, $end, $clientId, $limit);
        $periodLabel = $this->periodLabel($start, $end);
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $reportType) ?: 'rapport';

        return [$report, $periodLabel, $slug];
    }

    private function periodLabel(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): string
    {
        if ($start->toDateString() === $end->toDateString()) {
            return $start->format('d/m/Y');
        }

        return $start->format('d/m/Y').' — '.$end->format('d/m/Y');
    }
}
