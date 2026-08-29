<?php

namespace InovCom\Treasury\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class TreasuryRecurrence
{
    public const ONCE = 'once';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';
    public const YEARLY = 'yearly';

    /**
     * @param  list<string>  $paidDates  Y-m-d
     * @return list<Carbon>
     */
    public static function dates(
        string $frequency,
        CarbonInterface $start,
        CarbonInterface $from,
        CarbonInterface $to,
        array $paidDates = []
    ): array {
        $paid = array_flip($paidDates);
        $cursor = $start->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $begin = $from->copy()->startOfDay();
        $dates = [];

        if ($frequency === self::ONCE) {
            if ($cursor->lte($end) && !isset($paid[$cursor->toDateString()])) {
                $dates[] = $cursor->copy();
            }

            return $dates;
        }

        $guard = 0;
        while ($cursor->lt($begin) && $guard < 240) {
            $cursor = self::advance($cursor, $frequency);
            $guard++;
        }

        $guard = 0;
        while ($cursor->lte($end) && $guard < 240) {
            $key = $cursor->toDateString();
            if (!isset($paid[$key])) {
                $dates[] = $cursor->copy();
            }
            $cursor = self::advance($cursor, $frequency);
            $guard++;
        }

        return $dates;
    }

    private static function advance(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            self::WEEKLY => $date->copy()->addWeek(),
            self::YEARLY => $date->copy()->addYear(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
