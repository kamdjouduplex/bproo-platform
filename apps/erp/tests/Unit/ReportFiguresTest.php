<?php

namespace Tests\Unit;

use InovCom\Reporting\Support\ReportFigures;
use PHPUnit\Framework\TestCase;

class ReportFiguresTest extends TestCase
{
    public function test_margin_pct_is_benefit_over_revenue(): void
    {
        $this->assertSame(40.0, ReportFigures::marginPct(100_000, 60_000));
    }

    public function test_margin_pct_is_null_when_no_revenue(): void
    {
        $this->assertNull(ReportFigures::marginPct(0, 10_000));
    }

    public function test_share_pct_and_francs(): void
    {
        $this->assertSame(25.0, ReportFigures::sharePct(50_000, 200_000));
        $this->assertNull(ReportFigures::sharePct(10, 0));
        $this->assertSame(692_000.0, ReportFigures::francs(692_000.4));
    }
}
