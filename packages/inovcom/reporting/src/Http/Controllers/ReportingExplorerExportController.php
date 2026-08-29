<?php

namespace InovCom\Reporting\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Reporting\Services\ReportRunner;
use Symfony\Component\HttpFoundation\Response;

class ReportingExplorerExportController
{
    public function pdf(Request $request): Response
    {
        [$result, $periodLabel, $slug] = $this->resolveReport($request);

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        [$headers, $rows] = $this->flatten($result);

        $filename = 'reporting-'.$slug.'-'.now()->format('Y-m-d').'.pdf';

        $pdf = Pdf::loadView('inovcom-reporting::print.explorer-report', [
            'title' => (string) ($result['title'] ?? 'Rapport'),
            'periodLabel' => $periodLabel,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $this->meta($result),
            'shopName' => $settings['shop_name'] ?? ($tenant?->name),
            'generatedAt' => now(),
            'forPdf' => true,
            'autoPrint' => false,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function print(Request $request): View
    {
        [$result, $periodLabel, $slug] = $this->resolveReport($request);

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        [$headers, $rows] = $this->flatten($result);

        $printContext = PrintDocument::context(
            $request,
            'rapport-'.$slug,
            now()->format('Y-m-d'),
            'tenant.reporting.index'
        );

        return view('inovcom-reporting::print.explorer-report', array_merge([
            'title' => (string) ($result['title'] ?? 'Rapport'),
            'periodLabel' => $periodLabel,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $this->meta($result),
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

        $filters = [
            'module' => (string) $request->query('module', 'invoicing'),
            'report' => (string) $request->query('report_type', 'journal_factures'),
            'from' => (string) $request->query('date_from', now()->startOfMonth()->format('Y-m-d')),
            'to' => (string) $request->query('date_to', now()->format('Y-m-d')),
            'store_id' => $this->intOrNull($request->query('store_id')),
            'client_id' => $this->intOrNull($request->query('client_id')),
            'status' => (string) $request->query('status', ''),
            'user_id' => $this->intOrNull($request->query('user_id')),
            'category_id' => $this->intOrNull($request->query('category_id')),
            'item_id' => $this->intOrNull($request->query('item_id')),
            'amount_min' => $request->query('amount_min'),
            'amount_max' => $request->query('amount_max'),
            'sort' => (string) $request->query('sort', 'date'),
            'dir' => (string) $request->query('dir', 'desc'),
        ];

        $result = app(ReportRunner::class)->run($filters, 1, ReportRunner::EXPORT_MAX, true);
        $periodLabel = $filters['from'] === $filters['to']
            ? \Carbon\Carbon::parse($filters['from'])->format('d/m/Y')
            : \Carbon\Carbon::parse($filters['from'])->format('d/m/Y').' — '.\Carbon\Carbon::parse($filters['to'])->format('d/m/Y');
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $filters['report']) ?: 'rapport';

        return [$result, $periodLabel, $slug];
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function flatten(array $result): array
    {
        $headers = [];
        $keys = [];
        foreach ($result['headers'] ?? [] as $col) {
            $headers[] = $col['label'] ?? $col;
            $keys[] = $col['key'] ?? null;
        }

        $rows = [];
        foreach ($result['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($keys !== [] && $keys[0] !== null) {
                $line = [];
                foreach ($keys as $key) {
                    $line[] = $row[$key.'_label'] ?? $row[$key] ?? '';
                }
                $rows[] = $line;
            } else {
                $rows[] = array_values($row);
            }
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function meta(array $result): array
    {
        $totals = $result['totals'] ?? [];

        return [
            'count' => $result['paginator']->total() ?? count($result['rows'] ?? []),
            'total' => $totals['total'] ?? $totals['amount'] ?? null,
            'total_ttc' => $totals['ttc'] ?? null,
            'tva_total' => $totals['tva'] ?? null,
        ];
    }
}
