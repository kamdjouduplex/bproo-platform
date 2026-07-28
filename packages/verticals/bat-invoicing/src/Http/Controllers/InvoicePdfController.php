<?php

namespace InovCom\Facturation\Http\Controllers;

use App\Services\PdfService;
use InovCom\Facturation\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, PdfService $pdf, Invoice $invoice): \Illuminate\Http\Response
    {
        // Eager-load relations needed for the PDF template.
        $invoice->loadMissing(['lines', 'client', 'project']);

        $filename = match ($invoice->invoice_type) {
            'credit_note' => 'Avoir-' . $invoice->code . '.pdf',
            'proforma'    => 'Proforma-' . $invoice->code . '.pdf',
            default       => 'Facture-' . $invoice->code . '.pdf',
        };

        return $pdf->stream('pdf.invoice', ['invoice' => $invoice], $filename);
    }
}
