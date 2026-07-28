<?php

namespace InovCom\Devis\Services;

use Illuminate\Support\Facades\DB;
use InovCom\Devis\Models\Quote;
use InovCom\Facturation\Models\Invoice;
use InovCom\Facturation\Models\InvoiceLine;
use InovCom\Facturation\Support\InvoiceCodeGenerator;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Projets\Models\Project;

class CreateInvoiceFromQuoteService
{
    /**
     * @param  'full'|'advance'|'copy_lines'  $mode
     */
    public function create(Quote $quote, string $mode = 'full', int $advancePercent = 30): Invoice
    {
        if ($quote->status !== 'accepted') {
            throw new \RuntimeException(__('Seuls les devis acceptés peuvent être facturés.'));
        }

        if (!class_exists(Invoice::class)) {
            throw new \RuntimeException(__('Le module facturation n\'est pas disponible.'));
        }

        return DB::connection('tenant')->transaction(function () use ($quote, $mode, $advancePercent) {
            $quote->loadMissing(['lines', 'client', 'offer']);

            $project = Project::on('tenant')->where('quote_id', $quote->id)->first();

            $contract = null;
            if (class_exists(\InovCom\Maintenance\Models\MaintenanceContract::class)) {
                $contract = \InovCom\Maintenance\Models\MaintenanceContract::on('tenant')
                    ->where('quote_id', $quote->id)
                    ->first();
            }

            if (!$project && !$contract) {
                $category = $quote->offer?->category ?? ServiceCatalog::OFFER_PROJECT;
                throw new \RuntimeException(ServiceCatalog::invoicingBlockedMessage($category));
            }

            $billing = app(QuoteBillingService::class)->summarize($quote);

            if (!$billing->canInvoiceMore()) {
                throw new \RuntimeException(__('Ce devis est déjà entièrement facturé.'));
            }

            if ($mode === 'full' && $billing->hasFinalInvoice) {
                throw new \RuntimeException(__('Une facture de solde existe déjà pour ce devis.'));
            }

            $advancePercent = max(1, min(100, $advancePercent));

            if ($mode === 'advance') {
                $advanceAmount = $billing->advanceAmountForPercent($advancePercent);
                if ($advanceAmount <= 0) {
                    throw new \RuntimeException(__('Montant d\'acompte invalide : rien ne reste à facturer.'));
                }
            }

            $invoiceType = $mode === 'advance' ? 'proforma' : 'invoice';
            $code        = app(InvoiceCodeGenerator::class)->nextCode($invoiceType);

            $title = $this->buildTitle($quote, $mode, $advancePercent, $billing);

            $invoice = Invoice::create([
                'code'             => $code,
                'client_id'        => $quote->client_id,
                'project_id'       => $project?->id,
                'quote_id'         => $quote->id,
                'title'            => $title,
                'status'           => 'draft',
                'invoice_type'     => $invoiceType,
                'issue_date'       => now()->toDateString(),
                'due_date'         => now()->addDays(30)->toDateString(),
                'payment_terms'    => 30,
                'notes'            => $quote->notes,
                'currency'         => $quote->currency,
                'tax_rate'         => $quote->tax_rate,
                'discount_percent' => $quote->discount_percent,
                'total_ht'         => 0,
                'discount_amount'  => 0,
                'net_ht'           => 0,
                'tax_amount'       => 0,
                'total_ttc'        => 0,
                'amount_due'       => 0,
            ]);

            if ($mode === 'advance') {
                $this->createAdvanceLine($invoice, $quote, $advancePercent, $billing);
            } elseif ($billing->totalInvoicedTtc > 0.009) {
                $this->createSoldeLines($invoice, $quote, $billing);
            } else {
                $this->copyQuoteLines($invoice, $quote);
            }

            $this->recalculateInvoice($invoice);

            return $invoice->fresh(['lines', 'client']);
        });
    }

    private function buildTitle(Quote $quote, string $mode, int $advancePercent, QuoteBillingSummary $billing): string
    {
        if ($mode === 'advance') {
            $number = $billing->advanceInvoiceCount + 1;

            return __('Acompte n°:n — :percent% du reste — devis :code', [
                'n'       => $number,
                'percent' => $advancePercent,
                'code'    => $quote->code,
            ]);
        }

        if ($billing->totalInvoicedTtc > 0.009) {
            return __('Facture de solde — devis :code', ['code' => $quote->code]);
        }

        return __('Facture — devis :code', ['code' => $quote->code]);
    }

    private function copyQuoteLines(Invoice $invoice, Quote $quote): void
    {
        $position = 0;
        foreach ($quote->lines as $line) {
            if (($line->line_type ?? '') === 'section') {
                continue;
            }
            if ((float) $line->amount <= 0 && (float) $line->unit_price <= 0) {
                continue;
            }

            InvoiceLine::create([
                'invoice_id'  => $invoice->id,
                'position'    => $position++,
                'description' => $line->description,
                'quantity'    => $line->quantity,
                'unit_price'  => $line->unit_price,
                'amount'      => $line->amount,
            ]);
        }

        if ($position === 0) {
            InvoiceLine::create([
                'invoice_id'  => $invoice->id,
                'position'    => 0,
                'description' => $quote->title,
                'quantity'    => 1,
                'unit_price'  => $quote->net_ht ?: $quote->total_ht,
                'amount'      => $quote->net_ht ?: $quote->total_ht,
            ]);
        }
    }

    private function createSoldeLines(Invoice $invoice, Quote $quote, QuoteBillingSummary $billing): void
    {
        $remainingTtc = $billing->remainingToInvoiceTtc;
        $taxRate      = (float) $quote->tax_rate;
        $remainingHt  = $taxRate > 0
            ? round($remainingTtc / (1 + $taxRate / 100), 2)
            : $remainingTtc;

        $priorCodes = $billing->invoices->pluck('code')->join(', ');

        InvoiceLine::create([
            'invoice_id'  => $invoice->id,
            'position'    => 0,
            'description' => __('Solde devis :code', ['code' => $quote->code]),
            'quantity'    => 1,
            'unit_price'  => $remainingHt,
            'amount'      => $remainingHt,
        ]);

        if ($priorCodes !== '') {
            $deductionNote = __('Documents déjà facturés : :codes', ['codes' => $priorCodes]);
            $invoice->notes = trim(($invoice->notes ?? '') . "\n\n" . $deductionNote);
            $invoice->saveQuietly();
        }

        $invoice->tax_rate = $quote->tax_rate;
    }

    private function createAdvanceLine(
        Invoice $invoice,
        Quote $quote,
        int $percent,
        QuoteBillingSummary $billing
    ): void {
        $amount         = $billing->advanceAmountForPercent($percent);
        $advanceNumber  = $billing->advanceInvoiceCount + 1;
        $percentOfQuote = $billing->effectivePercentOfQuoteTotal($amount);

        InvoiceLine::create([
            'invoice_id'  => $invoice->id,
            'position'    => 0,
            'description' => __('Acompte n°:n : :percent% du reste à facturer (:quote_percent% du devis) — :title', [
                'n'             => $advanceNumber,
                'percent'       => $percent,
                'quote_percent' => number_format($percentOfQuote, 1, ',', ' '),
                'title'         => $quote->title,
            ]),
            'quantity'    => 1,
            'unit_price'  => $amount,
            'amount'      => $amount,
        ]);

        $invoice->discount_percent = 0;
        $invoice->tax_rate         = 0;
    }

    private function recalculateInvoice(Invoice $invoice): void
    {
        $invoice->load('lines');
        $totalHt = (float) $invoice->lines->sum('amount');
        $discount = round($totalHt * ((float) $invoice->discount_percent / 100), 2);
        $netHt = round($totalHt - $discount, 2);
        $tax = round($netHt * ((float) $invoice->tax_rate / 100), 2);
        $totalTtc = round($netHt + $tax, 2);

        $invoice->update([
            'total_ht'        => $totalHt,
            'discount_amount' => $discount,
            'net_ht'          => $netHt,
            'tax_amount'      => $tax,
            'total_ttc'       => $totalTtc,
            'amount_due'      => $totalTtc,
        ]);
    }
}
