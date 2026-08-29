<?php

namespace Tests\Unit;

use InovCom\Purchases\Support\PurchaseVatCalculator;
use PHPUnit\Framework\TestCase;

class PurchaseVatCalculatorTest extends TestCase
{
    public function test_extracts_ht_from_ttc_and_uses_ht_as_stock_cost_when_deductible(): void
    {
        $result = PurchaseVatCalculator::fromEntered(
            118_000,
            1,
            18,
            PurchaseVatCalculator::MODE_TTC,
            true,
            true
        );

        $this->assertSame(100_000.0, $result['unit_price_ht']);
        $this->assertSame(18_000.0, $result['unit_vat']);
        $this->assertSame(118_000.0, $result['unit_price_ttc']);
        $this->assertSame(100_000.0, $result['unit_price']);
        $this->assertSame(100_000.0, $result['line_total']);
    }

    public function test_adds_vat_when_prices_are_entered_ht(): void
    {
        $result = PurchaseVatCalculator::fromEntered(
            100_000,
            2,
            18,
            PurchaseVatCalculator::MODE_HT,
            true,
            true
        );

        $this->assertSame(100_000.0, $result['unit_price_ht']);
        $this->assertSame(118_000.0, $result['unit_price_ttc']);
        $this->assertSame(36_000.0, $result['vat_amount']);
        $this->assertSame(200_000.0, $result['line_total_ht']);
        $this->assertSame(236_000.0, $result['line_total_ttc']);
        $this->assertSame(100_000.0, $result['unit_price']);
        $this->assertSame(200_000.0, $result['line_total']);
    }

    public function test_non_deductible_vat_posts_ttc_as_stock_cost(): void
    {
        $result = PurchaseVatCalculator::fromEntered(
            118_000,
            1,
            18,
            PurchaseVatCalculator::MODE_TTC,
            true,
            false
        );

        $this->assertSame(100_000.0, $result['unit_price_ht']);
        $this->assertSame(118_000.0, $result['unit_price']);
        $this->assertSame(118_000.0, $result['line_total']);
    }

    public function test_without_vat_keeps_entered_price_as_cost(): void
    {
        $result = PurchaseVatCalculator::fromEntered(
            50_000,
            3,
            18,
            PurchaseVatCalculator::MODE_TTC,
            false,
            true
        );

        $this->assertSame(50_000.0, $result['unit_price_ht']);
        $this->assertSame(50_000.0, $result['unit_price_ttc']);
        $this->assertSame(0.0, $result['vat_amount']);
        $this->assertSame(150_000.0, $result['line_total']);
    }
}
