<?php

namespace InovCom\Devis\Http\Controllers;

use App\Services\PdfService;
use InovCom\Devis\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuotePdfController extends Controller
{
    public function __invoke(Request $request, PdfService $pdf, Quote $quote): \Illuminate\Http\Response
    {
        // Eager-load relations needed for the PDF template.
        $quote->loadMissing(['lines', 'client']);

        $filename = 'Devis-' . $quote->code . '.pdf';

        return $pdf->stream('pdf.quote', ['quote' => $quote], $filename);
    }
}
