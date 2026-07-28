<?php

namespace InovCom\Devis\Services;

use InovCom\Devis\Models\Quote;
use InovCom\Facturation\Models\Invoice;
use InovCom\Facturation\Models\InvoicePayment;

class QuoteBillingService
{
    public function summarize(Quote $quote): QuoteBillingSummary
    {
        $quoteTotalTtc = round((float) $quote->total_ttc, 2);

        if (!class_exists(Invoice::class)) {
            return QuoteBillingSummary::empty($quote, $quoteTotalTtc);
        }

        $invoices = Invoice::on('tenant')
            ->where('quote_id', $quote->id)
            ->where('status', '!=', 'cancelled')
            ->with(['payments'])
            ->orderBy('issue_date')
            ->orderBy('created_at')
            ->get();

        $totalInvoicedTtc = round((float) $invoices->sum('total_ttc'), 2);
        $totalAdvanceInvoicedTtc = round(
            (float) $invoices->where('invoice_type', 'proforma')->sum('total_ttc'),
            2
        );
        $totalPaidTtc = round((float) $invoices->sum('amount_paid'), 2);
        $totalDueTtc = round((float) $invoices->sum(fn (Invoice $i) => max(0, (float) $i->amount_due)), 2);
        $remainingToInvoiceTtc = max(0, round($quoteTotalTtc - $totalInvoicedTtc, 2));

        $advanceInvoiceCount = $invoices->where('invoice_type', 'proforma')->count();
        $hasFinalInvoice = $invoices->where('invoice_type', 'invoice')->isNotEmpty();

        $invoiceRows = $invoices
            ->sortByDesc(fn (Invoice $i) => $i->issue_date?->format('Y-m-d') . $i->created_at?->format('Y-m-d H:i:s'))
            ->values()
            ->map(fn (Invoice $invoice) => $this->mapInvoiceRow($invoice));

        $payments = $invoices
            ->flatMap(function (Invoice $invoice) {
                return $invoice->payments->map(fn (InvoicePayment $payment) => $this->mapPaymentRow($payment, $invoice));
            })
            ->sortByDesc('sort_key')
            ->values();

        return new QuoteBillingSummary(
            quote: $quote,
            quoteTotalTtc: $quoteTotalTtc,
            totalInvoicedTtc: $totalInvoicedTtc,
            totalAdvanceInvoicedTtc: $totalAdvanceInvoicedTtc,
            totalPaidTtc: $totalPaidTtc,
            totalDueTtc: $totalDueTtc,
            remainingToInvoiceTtc: $remainingToInvoiceTtc,
            advanceInvoiceCount: $advanceInvoiceCount,
            hasFinalInvoice: $hasFinalInvoice,
            invoices: $invoiceRows,
            payments: $payments,
        );
    }

    private function mapInvoiceRow(Invoice $invoice): array
    {
        return [
            'id'           => $invoice->id,
            'code'         => $invoice->code,
            'title'        => $invoice->title,
            'invoice_type' => $invoice->invoice_type,
            'type_label'   => $this->invoiceTypeLabel($invoice->invoice_type),
            'status'       => $invoice->status,
            'status_label' => $this->invoiceStatusLabel($invoice->status),
            'status_class' => $this->invoiceStatusClass($invoice->status),
            'total_ttc'    => (float) $invoice->total_ttc,
            'amount_paid'  => (float) $invoice->amount_paid,
            'amount_due'   => (float) $invoice->amount_due,
            'issue_date'   => $invoice->issue_date?->format('d/m/Y'),
            'currency'     => $invoice->currency,
            'payments'     => $invoice->payments
                ->map(fn (InvoicePayment $p) => $this->mapPaymentRow($p, $invoice))
                ->values()
                ->all(),
        ];
    }

    private function mapPaymentRow(InvoicePayment $payment, Invoice $invoice): array
    {
        $payment->setRelation('invoice', $invoice);

        return [
            'id'                  => $payment->id,
            'invoice_id'          => $invoice->id,
            'invoice_code'        => $invoice->code,
            'amount'              => (float) $payment->amount,
            'payment_date'        => $payment->payment_date?->format('d/m/Y'),
            'payment_method'      => $payment->payment_method,
            'payment_method_label'=> $payment->paymentMethodLabel(),
            'reference'           => $payment->reference,
            'receipt_code'        => $payment->receiptCode(),
            'currency'            => $invoice->currency,
            'sort_key'            => ($payment->payment_date?->format('Y-m-d') ?? '') . str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
        ];
    }

    private function invoiceTypeLabel(?string $type): string
    {
        return match ($type) {
            'proforma'    => __('Acompte (proforma)'),
            'credit_note' => __('Avoir'),
            default       => __('Facture'),
        };
    }

    private function invoiceStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft'     => __('Brouillon'),
            'sent'      => __('Envoyée'),
            'paid'      => __('Payée'),
            'overdue'   => __('En retard'),
            'cancelled' => __('Annulée'),
            default     => (string) $status,
        };
    }

    private function invoiceStatusClass(?string $status): string
    {
        return match ($status) {
            'draft'     => 'bg-slate-100 text-slate-600',
            'sent'      => 'bg-blue-100 text-blue-700',
            'paid'      => 'bg-emerald-100 text-emerald-700',
            'overdue'   => 'bg-amber-100 text-amber-800',
            'cancelled' => 'bg-red-100 text-red-700',
            default     => 'bg-slate-100 text-slate-500',
        };
    }
}
