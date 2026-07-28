<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Thin wrapper around barryvdh/laravel-dompdf.
 *
 * Usage:
 *   app(PdfService::class)->download('pdf.quote', ['quote' => $quote], "Devis-{$quote->code}.pdf");
 *   app(PdfService::class)->stream('pdf.invoice', ['invoice' => $invoice], "Facture-{$invoice->code}.pdf");
 *
 * Requires:
 *   composer require barryvdh/laravel-dompdf
 */
class PdfService
{
    protected array $options = [
        'defaultFont' => 'DejaVu Sans',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => false,
    ];

    /**
     * Return a download response.
     */
    public function download(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setOptions($this->options)
            ->download($filename);
    }

    public function output(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)
            ->setOptions($this->options)
            ->output();
    }

    /**
     * Return an inline (stream) response — opens in browser PDF viewer.
     */
    public function stream(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setOptions($this->options)
            ->stream($filename);
    }
}
