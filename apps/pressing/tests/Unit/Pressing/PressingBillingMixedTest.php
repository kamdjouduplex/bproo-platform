<?php

namespace Tests\Unit\Pressing;

use PHPUnit\Framework\TestCase;
use Pressing\Support\PressingBilling;

class PressingBillingMixedTest extends TestCase
{
    public function test_mixed_order_sums_fixed_and_per_kg_lines(): void
    {
        $items = [
            [
                'pricing_mode' => PressingBilling::ARTICLE_FIXED,
                'quantity' => 3,
                'unit_price' => 1000,
            ],
            [
                'pricing_mode' => PressingBilling::ARTICLE_PER_KG,
                'weight_kg' => 2.5,
                'price_per_kg' => 1500,
            ],
        ];

        $this->assertSame(3000.0, PressingBilling::lineTotal(PressingBilling::MODE_MIXED, $items[0]));
        $this->assertSame(3750.0, PressingBilling::lineTotal(PressingBilling::MODE_MIXED, $items[1]));
        $this->assertSame(6750.0, PressingBilling::orderSubtotal(PressingBilling::MODE_MIXED, $items, null, null));
    }

    public function test_is_line_per_kg_respects_item_mode_in_mixed(): void
    {
        $this->assertFalse(PressingBilling::isLinePerKg(PressingBilling::MODE_MIXED, [
            'pricing_mode' => PressingBilling::ARTICLE_FIXED,
        ]));
        $this->assertTrue(PressingBilling::isLinePerKg(PressingBilling::MODE_MIXED, [
            'pricing_mode' => PressingBilling::ARTICLE_PER_KG,
        ]));
    }

    public function test_stored_item_pricing_mode(): void
    {
        $this->assertSame(
            PressingBilling::ARTICLE_PER_KG,
            PressingBilling::storedItemPricingMode(PressingBilling::MODE_MIXED, [
                'pricing_mode' => PressingBilling::ARTICLE_PER_KG,
            ])
        );
        $this->assertSame(
            PressingBilling::ARTICLE_FIXED,
            PressingBilling::storedItemPricingMode(PressingBilling::MODE_MIXED, [
                'pricing_mode' => PressingBilling::ARTICLE_FIXED,
            ])
        );
    }
}
