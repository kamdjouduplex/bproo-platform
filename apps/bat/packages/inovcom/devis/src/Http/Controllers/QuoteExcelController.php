<?php

namespace InovCom\Devis\Http\Controllers;

use InovCom\Devis\Exports\QuoteExport;
use InovCom\Devis\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuoteExcelController extends Controller
{
    public function __invoke(Request $request, Quote $quote): BinaryFileResponse
    {
        $user = auth('tenant')->user();
        if (!$user || !$user->hasPermission('devis.export')) {
            abort(403);
        }

        $quote->loadMissing(['lines', 'client']);

        $filename = 'Devis-' . $quote->code . '.xlsx';

        return Excel::download(new QuoteExport($quote), $filename);
    }
}
