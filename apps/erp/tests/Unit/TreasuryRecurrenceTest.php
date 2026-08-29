<?php

namespace Tests\Unit;

use Carbon\Carbon;
use InovCom\Treasury\Support\TreasuryRecurrence;
use PHPUnit\Framework\TestCase;

class TreasuryRecurrenceTest extends TestCase
{
    public function test_monthly_occurrences_skip_paid_dates(): void
    {
        $dates = TreasuryRecurrence::dates(
            TreasuryRecurrence::MONTHLY,
            Carbon::parse('2026-09-05'),
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-12-31'),
            ['2026-10-05']
        );

        $this->assertSame(
            ['2026-09-05', '2026-11-05', '2026-12-05'],
            array_map(fn (Carbon $d) => $d->toDateString(), $dates)
        );
    }

    public function test_once_includes_overdue_unpaid(): void
    {
        $dates = TreasuryRecurrence::dates(
            TreasuryRecurrence::ONCE,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertCount(1, $dates);
        $this->assertSame('2026-08-01', $dates[0]->toDateString());
    }
}
