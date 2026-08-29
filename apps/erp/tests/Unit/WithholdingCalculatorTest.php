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

    public function test_cameroon_vat_withholding_rounds_to_whole_francs_and_balances(): void
    {
        $withholding = WithholdingCalculator::amountFromBaseAndRate(88_500, 19.25);
        $this->assertSame(17_036.0, $withholding);

        $cash = WithholdingCalculator::cashDue(88_500, [['amount' => $withholding]]);
        $this->assertSame(71_464.0, $cash);

        $summary = WithholdingCalculator::summarize(88_500, 0, $cash, [
            ['amount' => $withholding],
        ]);

        $this->assertSame(88_500.0, $summary['settled']);
        $this->assertSame(0.0, $summary['remaining']);
        $this->assertFalse($summary['exceeds']);
    }
}
