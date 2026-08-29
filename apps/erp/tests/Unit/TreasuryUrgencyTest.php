<?php

namespace Tests\Unit;

use Carbon\Carbon;
use InovCom\Treasury\Support\TreasuryUrgency;
use PHPUnit\Framework\TestCase;

class TreasuryUrgencyTest extends TestCase
{
    public function test_classifies_overdue_urgent_upcoming_and_planned(): void
    {
        $today = Carbon::parse('2026-09-01');

        $overdue = TreasuryUrgency::classify(Carbon::parse('2026-08-20'), $today->copy());
        $this->assertSame(TreasuryUrgency::OVERDUE, $overdue['key']);

        $urgent = TreasuryUrgency::classify(Carbon::parse('2026-09-05'), $today->copy());
        $this->assertSame(TreasuryUrgency::URGENT, $urgent['key']);
        $this->assertSame(4, $urgent['days']);

        $upcoming = TreasuryUrgency::classify(Carbon::parse('2026-09-20'), $today->copy());
        $this->assertSame(TreasuryUrgency::UPCOMING, $upcoming['key']);

        $planned = TreasuryUrgency::classify(Carbon::parse('2026-11-01'), $today->copy());
        $this->assertSame(TreasuryUrgency::PLANNED, $planned['key']);
    }

    public function test_paid_overrides_proximity(): void
    {
        $today = Carbon::parse('2026-09-01');
        $result = TreasuryUrgency::classify(Carbon::parse('2026-08-20'), $today, paid: true);

        $this->assertSame(TreasuryUrgency::PAID, $result['key']);
    }
}
