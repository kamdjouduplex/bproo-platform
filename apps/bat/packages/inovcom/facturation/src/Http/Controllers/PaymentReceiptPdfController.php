<?php

namespace InovCom\Facturation\Http\Controllers;

use App\Services\PdfService;
use InovCom\Facturation\Models\Invoice;
use InovCom\Facturation\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentReceiptPdfController extends Controller
{
    public function __invoke(
        Request $request,
        PdfService $pdf,
        Invoice $invoice,
        InvoicePayment $payment
    ): \Illuminate\Http\Response {
        if ((int) $payment->invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        $invoice->loadMissing(['client', 'project', 'quote']);
        $payment->loadMissing(['recordedByUser']);

        $payments = $invoice->payments()->orderBy('payment_date')->orderBy('id')->get();
        $cumulativePaid = 0.0;
        foreach ($payments as $row) {
            $cumulativePaid += (float) $row->amount;
            if ((int) $row->id === (int) $payment->id) {
                break;
            }
        }

        $balanceAfter = round((float) $invoice->total_ttc - $cumulativePaid, 2);
        $receiptCode  = $payment->receiptCode();
        $filename     = $receiptCode . '.pdf';

        return $pdf->stream('pdf.payment-receipt', [
            'invoice'        => $invoice,
            'payment'        => $payment,
            'receiptCode'    => $receiptCode,
            'cumulativePaid' => $cumulativePaid,
            'balanceAfter'   => $balanceAfter,
        ], $filename);
    }
}
