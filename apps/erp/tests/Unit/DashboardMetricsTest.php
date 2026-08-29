<?php

namespace Tests\Unit;

use App\Support\DashboardMetrics;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DashboardMetricsTest extends TestCase
{
    public function test_net_ht_subtracts_discount_and_never_goes_negative(): void
    {
        $this->assertSame(90_000.0, DashboardMetrics::netHt(100_000, 10_000));
        $this->assertSame(0.0, DashboardMetrics::netHt(8_000, 10_000));
    }

    public function test_billed_ttc_is_ht_plus_vat(): void
    {
        $ht = 1_245_500.0;
        $vat = 221_775.0;

        $this->assertSame(1_467_275.0, DashboardMetrics::billedTtc($ht, $vat));
    }

    public function test_allocate_ht_and_remaining_ht_are_complementary(): void
    {
        $ht = 100_000.0;
        $vat = 19_250.0;
        $ttc = DashboardMetrics::billedTtc($ht, $vat);
        $paid = 59_625.0;
        $balance = $ttc - $paid;

        $collectedHt = DashboardMetrics::allocateHt($paid, $ht, $ttc);
        $remainingHt = DashboardMetrics::allocateHt($balance, $ht, $ttc);

        $this->assertSame($ht, $collectedHt + $remainingHt);
        $this->assertSame($ttc, $paid + $balance);
    }

    public function test_cameroon_vat_withholding_settles_full_ht(): void
    {
        $ht = 74_214.0;
        $vat = 14_286.0;
        $ttc = DashboardMetrics::billedTtc($ht, $vat);
        $this->assertSame(88_500.0, $ttc);

        $withheldVat = 17_036.0;
        $cash = 71_464.0;
        $settled = $cash + $withheldVat;
        $this->assertSame($ttc, $settled);

        $collectedHt = DashboardMetrics::allocateHt($settled, $ht, $ttc);
        $this->assertSame($ht, $collectedHt);

        $vatFromCash = DashboardMetrics::allocateVat($cash, $vat, $ttc);
        $declared = DashboardMetrics::vatToDeclare($vat, $withheldVat);

        $this->assertGreaterThanOrEqual(0.0, $vatFromCash);
        $this->assertSame(0.0, $declared);
    }

    public function test_vat_to_declare_is_collected_minus_withheld(): void
    {
        $this->assertSame(198_750.0, DashboardMetrics::vatToDeclare(224_190, 25_440));
        $this->assertSame(0.0, DashboardMetrics::vatToDeclare(10_000, 12_000));
    }

    public function test_trend_percent_vs_previous_month(): void
    {
        $this->assertSame(18.4, DashboardMetrics::trendPercent(1_245_500, 1_052_300));
        $this->assertSame(100.0, DashboardMetrics::trendPercent(50_000, 0));
        $this->assertNull(DashboardMetrics::trendPercent(0, 0));
        $this->assertSame(-10.0, DashboardMetrics::trendPercent(90, 100));
    }

    public function test_payment_urgency_matches_mockup_rules(): void
    {
        $today = Carbon::parse('2026-08-29');

        $this->assertSame(
            DashboardMetrics::URGENCY_URGENT,
            DashboardMetrics::paymentUrgency(Carbon::parse('2026-08-20'), $today)
        );
        $this->assertSame(
            DashboardMetrics::URGENCY_WATCH,
            DashboardMetrics::paymentUrgency(Carbon::parse('2026-09-03'), $today)
        );
        $this->assertSame(
            DashboardMetrics::URGENCY_NORMAL,
            DashboardMetrics::paymentUrgency(Carbon::parse('2026-09-20'), $today)
        );
        $this->assertSame(
            DashboardMetrics::URGENCY_NORMAL,
            DashboardMetrics::paymentUrgency(null, $today)
        );
    }

    public function test_share_percents_sum_to_one_hundred(): void
    {
        $percents = DashboardMetrics::sharePercents([
            'Transport' => 48_000,
            'Garage' => 22_000,
            'Pièces' => 18_000,
            'Autres' => 12_000,
        ]);

        $this->assertSame(100.0, array_sum($percents));
        $this->assertSame(48.0, $percents['Transport']);
        $this->assertSame(22.0, $percents['Garage']);
    }

    public function test_vat_name_and_withholding_detection(): void
    {
        $this->assertTrue(DashboardMetrics::isVatName('TVA 19.25%'));
        $this->assertTrue(DashboardMetrics::isVatName('VAT'));
        $this->assertFalse(DashboardMetrics::isVatName('IRCM'));
        $this->assertTrue(DashboardMetrics::isVatWithholding('tva_retenue', 'TVA retenue'));
        $this->assertFalse(DashboardMetrics::isVatWithholding('is', 'Impôt sur les sociétés'));
    }

    public function test_amounts_round_to_whole_francs(): void
    {
        $this->assertSame(17_036.0, DashboardMetrics::round(17_036.25));
        $this->assertSame(17_036.0, DashboardMetrics::round(17_035.5));
    }
}
