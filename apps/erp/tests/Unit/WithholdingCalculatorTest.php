<?php

namespace Tests\Unit;

use InovCom\InvoicePayments\Support\WithholdingCalculator;
use PHPUnit\Framework\TestCase;

class WithholdingCalculatorTest extends TestCase
{
    public function test_invoice_is_settled_when_cash_plus_withholdings_equal_total(): void
    {
        $summary = WithholdingCalculator::summarize(1_000_000, 0, 900_000, [
            ['amount' => 70_000],
            ['amount' => 30_000],
        ]);

        $this->assertSame(900_000.0, $summary['cash_received']);
        $this->assertSame(100_000.0, $summary['withholding_total']);
        $this->assertSame(1_000_000.0, $summary['settled']);
        $this->assertSame(0.0, $summary['remaining']);
        $this->assertFalse($summary['exceeds']);
    }

    public function test_partial_settlement_keeps_remaining_balance(): void
    {
        $summary = WithholdingCalculator::summarize(1_000_000, 0, 400_000, [
            ['amount' => 50_000],
        ]);

        $this->assertSame(450_000.0, $summary['settled']);
        $this->assertSame(550_000.0, $summary['remaining']);
        $this->assertFalse($summary['exceeds']);
    }

    public function test_detects_when_settled_amount_exceeds_balance(): void
    {
        $summary = WithholdingCalculator::summarize(100_000, 20_000, 70_000, [
            ['amount' => 20_000],
        ]);

        $this->assertTrue($summary['exceeds']);
        $this->assertSame(-10_000.0, $summary['remaining']);
    }

    public function test_amount_from_base_and_rate(): void
    {
        $this->assertSame(70_000.0, WithholdingCalculator::amountFromBaseAndRate(1_000_000, 7));
        $this->assertSame(0.0, WithholdingCalculator::amountFromBaseAndRate(1_000_000, 0));
    }

    public function test_vat_withholding_uses_invoice_vat_not_percent_of_ttc(): void
    {
        $ht = 770_000.0;
        $vat = 148_225.0;
        $ttc = 918_225.0;

        $wrongPercentOnTtc = WithholdingCalculator::amountFromBaseAndRate($ttc, 19.25);
        $this->assertSame(176_758.0, $wrongPercentOnTtc);
        $this->assertNotEquals($vat, $wrongPercentOnTtc);

        $amount = WithholdingCalculator::suggestInvoiceVatAmount($vat, 0, $ttc, $ttc);
        $this->assertSame($vat, $amount);

        $cash = WithholdingCalculator::cashDue($ttc, [['amount' => $amount]]);
        $this->assertSame($ht, $cash);

        $summary = WithholdingCalculator::summarize($ttc, 0, $cash, [['amount' => $amount]]);
        $this->assertSame($ttc, $summary['settled']);
        $this->assertSame(0.0, $summary['remaining']);
        $this->assertFalse($summary['exceeds']);
    }

    public function test_is_is_calculated_from_profit_and_rate(): void
    {
        $this->assertSame(150_000.0, WithholdingCalculator::amountFromBaseAndRate(500_000, 30));
        $this->assertSame(16_940.0, WithholdingCalculator::amountFromBaseAndRate(770_000, 2.2));
    }

    public function test_partial_vat_withholding_is_prorated_then_capped(): void
    {
        $vat = 148_225.0;
        $half = WithholdingCalculator::suggestInvoiceVatAmount($vat, 0, 459_112, 918_225);
        $this->assertSame(74_112.0, $half);

        $rest = WithholdingCalculator::suggestInvoiceVatAmount($vat, $half, 459_113, 459_113);
        $this->assertSame(74_113.0, $rest);
        $this->assertSame($vat, $half + $rest);
    }
}
