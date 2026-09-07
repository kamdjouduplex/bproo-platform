<?php

namespace Tests\Unit;

use Carbon\Carbon;
use InovCom\InvoicePayments\Support\CollectionPeriodRecap;
use InovCom\InvoicePayments\Support\PaymentSettlementLines;
use PHPUnit\Framework\TestCase;

class CollectionPeriodRecapTest extends TestCase
{
    public function test_windows_are_previous_and_current_calendar_months(): void
    {
        $windows = CollectionPeriodRecap::windows(Carbon::parse('2026-09-07'));

        $this->assertSame('2026-08-01', $windows['previous']['from']);
        $this->assertSame('2026-08-31', $windows['previous']['to']);
        $this->assertSame('août 2026', $windows['previous']['label']);
        $this->assertSame('2026-09-01', $windows['current']['from']);
        $this->assertSame('2026-09-30', $windows['current']['to']);
        $this->assertSame('septembre 2026', $windows['current']['label']);
    }

    public function test_from_payments_sums_ht_ttc_and_vat_split(): void
    {
        $withheld = PaymentSettlementLines::build(
            770_000,
            918_225,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 148_225, 'effect' => 'add', 'is_vat' => true]],
            770_000,
            918_225,
            [['type_code' => 'tva_retenue', 'type_name' => 'TVA retenue', 'kind' => 'vat', 'amount' => 148_225, 'rate' => 19.25]],
            0,
        );
        $cash = PaymentSettlementLines::build(
            100_000,
            119_250,
            [['name' => 'TVA', 'rate' => 19.25, 'amount' => 19_250, 'effect' => 'add', 'is_vat' => true]],
            119_250,
            119_250,
            [],
            0,
        );

        $totals = PaymentSettlementLines::addFiscalSlices(
            PaymentSettlementLines::fiscalSlice($withheld),
            PaymentSettlementLines::fiscalSlice($cash),
        );

        $this->assertSame(870_000.0, $totals['ht']);
        $this->assertSame(1_037_475.0, $totals['ttc']);
        $this->assertSame(148_225.0, $totals['vat_withheld']);
        $this->assertSame(19_250.0, $totals['vat_collected']);
    }
}
