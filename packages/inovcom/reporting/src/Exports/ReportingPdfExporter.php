<?php

namespace InovCom\Reporting\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ReportingPdfExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|float|int|null>>  $rows
     * @param  array{count?: int|float|null, total?: float|null, total_ttc?: float|null, tva_total?: float|null}  $meta
     */
    public static function download(
        string $filename,
        array $headers,
        array $rows,
        string $title = '',
        string $periodLabel = '',
        array $meta = [],
        ?string $shopName = null
    ): Response {
        $pdf = Pdf::loadView('inovcom-reporting::print.explorer-report', [
            'title' => $title,
            'periodLabel' => $periodLabel,
            'headers' => $headers,
            'rows' => $rows,
            'meta' => $meta,
            'shopName' => $shopName,
            'generatedAt' => now(),
            'forPdf' => true,
            'autoPrint' => false,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
