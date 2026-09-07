<?php

namespace InovCom\InvoicePayments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Models\InvoicePaymentAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePaymentAttachmentController
{
    public function __invoke(Request $request, InvoicePayment $invoicePayment, InvoicePaymentAttachment $invoicePaymentAttachment): StreamedResponse
    {
        $attachment = $invoicePaymentAttachment;
        if ((int) $attachment->invoice_payment_id !== (int) $invoicePayment->id) {
            abort(404);
        }

        if (!$attachment->path || !Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        $downloadName = $attachment->original_name ?: ($attachment->label ?: 'attestation-retenue');
        $forceDownload = $request->boolean('download');
        $headers = [];
        if ($attachment->mime_type) {
            $headers['Content-Type'] = $attachment->mime_type;
        } elseif ($attachment->isPdf()) {
            $headers['Content-Type'] = 'application/pdf';
        }

        if (! $forceDownload && ($attachment->isPdf() || $attachment->isImage())) {
            return Storage::disk('public')->response($attachment->path, $downloadName, $headers, 'inline');
        }

        return Storage::disk('public')->download($attachment->path, $downloadName, $headers);
    }
}
