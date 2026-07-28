<?php

namespace Tests\Unit;

use App\Support\DocumentTaxCalculator;
use PHPUnit\Framework\TestCase;

class DocumentTaxCalculatorTest extends TestCase
{
    public function test_withholding_is_subtracted_from_ht_not_ttc(): void
    {
        $netHt = 1_620_000.0;
        $result = DocumentTaxCalculator::summarize($netHt, [
            ['name' => 'TVA', 'mode' => 'percent', 'rate' => 19.25, 'effect' => DocumentTaxCalculator::EFFECT_ADD],
            ['name' => 'IS', 'mode' => 'percent', 'rate' => 2.2, 'effect' => DocumentTaxCalculator::EFFECT_SUBTRACT],
        ]);

        $this->assertSame(311_850.0, $result['additive']);
        $this->assertSame(35_640.0, $result['subtractive']);
        $this->assertSame(1_931_850.0, $result['ttc']);
        $this->assertSame(1_584_360.0, $result['total']);
    }

    public function test_vat_only_total_equals_ttc(): void
    {
        $netHt = 1_000.0;
        $result = DocumentTaxCalculator::summarize($netHt, [
            ['name' => 'TVA', 'mode' => 'percent', 'rate' => 19.25, 'effect' => DocumentTaxCalculator::EFFECT_ADD],
        ]);

        $this->assertSame(1_192.5, $result['ttc']);
        $this->assertSame(1_192.5, $result['total']);
    }
}
