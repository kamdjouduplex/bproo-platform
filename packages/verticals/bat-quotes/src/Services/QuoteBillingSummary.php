<?php

namespace InovCom\Devis\Services;

use Illuminate\Support\Collection;
use InovCom\Devis\Models\Quote;

class QuoteBillingSummary
{
    /**
     * @param  Collection<int, array<string, mixed>>  $invoices
     * @param  Collection<int, array<string, mixed>>  $payments
     */
    public function __construct(
        public Quote $quote,
        public float $quoteTotalTtc,
        public float $totalInvoicedTtc,
        public float $totalAdvanceInvoicedTtc,
        public float $totalPaidTtc,
        public float $totalDueTtc,
        public float $remainingToInvoiceTtc,
        public int $advanceInvoiceCount,
        public bool $hasFinalInvoice,
        public Collection $invoices,
        public Collection $payments,
    ) {}

    public static function empty(Quote $quote, float $quoteTotalTtc): self
    {
        return new self(
            quote: $quote,
            quoteTotalTtc: $quoteTotalTtc,
            totalInvoicedTtc: 0,
            totalAdvanceInvoicedTtc: 0,
            totalPaidTtc: 0,
            totalDueTtc: 0,
            remainingToInvoiceTtc: $quoteTotalTtc,
            advanceInvoiceCount: 0,
            hasFinalInvoice: false,
            invoices: collect(),
            payments: collect(),
        );
    }

    public function invoicedPercentOfQuote(): float
    {
        if ($this->quoteTotalTtc <= 0) {
            return 0;
        }

        return min(100, round($this->totalInvoicedTtc / $this->quoteTotalTtc * 100, 1));
    }

    public function paidPercentOfQuote(): float
    {
        if ($this->quoteTotalTtc <= 0) {
            return 0;
        }

        return min(100, round($this->totalPaidTtc / $this->quoteTotalTtc * 100, 1));
    }

    public function canInvoiceMore(): bool
    {
        return $this->remainingToInvoiceTtc > 0.009;
    }

    public function canCreateFinalInvoice(): bool
    {
        return $this->canInvoiceMore() && !$this->hasFinalInvoice;
    }

    public function advanceAmountForPercent(int $percent): float
    {
        $percent = max(1, min(100, $percent));

        return round($this->remainingToInvoiceTtc * $percent / 100, 2);
    }

    public function effectivePercentOfQuoteTotal(float $amount): float
    {
        if ($this->quoteTotalTtc <= 0) {
            return 0;
        }

        return round($amount / $this->quoteTotalTtc * 100, 1);
    }
}
