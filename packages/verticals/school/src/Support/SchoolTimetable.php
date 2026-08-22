<?php

namespace School\Support;

use School\Models\SchoolOption;

final class SchoolTimetable
{
    /**
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
        ];
    }

    public static function weekdayLabel(int $day): string
    {
        return self::weekdays()[$day] ?? (string) $day;
    }

    /** ISO-8601 weekday: 1 = lundi … 7 = dimanche. */
    public static function weekdayFromDate(string $date): int
    {
        $ts = strtotime($date);

        return $ts === false ? 0 : (int) date('N', $ts);
    }

    /**
     * @param  iterable<mixed>  $slots
     * @return array{periods: list<array{start:string,end:string,label:string}>, grid: array<string, array<int, list<mixed>>>}
     */
    public static function gridFromSlots(iterable $slots): array
    {
        $slots = collect($slots);
        $periods = self::insertBreakRows(self::periods());
        $knownStarts = collect($periods)->pluck('start');
        $extraStarts = $slots->map(fn ($s) => $s->startHm())->unique()->reject(fn ($t) => $knownStarts->contains($t));
        foreach ($extraStarts as $start) {
            $match = $slots->first(fn ($s) => $s->startHm() === $start);
            $periods[] = [
                'start' => $start,
                'end' => $match?->endHm() ?: $start,
                'label' => $start,
                'type' => 'lesson',
            ];
        }
        usort($periods, fn ($a, $b) => $a['start'] <=> $b['start']);

        $grid = [];
        foreach ($slots as $slot) {
            $grid[$slot->startHm()][(int) $slot->weekday][] = $slot;
        }

        return ['periods' => $periods, 'grid' => $grid];
    }

    /**
     * @return array{
     *   start:string,end:string,lesson:int,gap:int,
     *   break1:int,break2:int,break1After:int,break2After:int
     * }
     */
    public static function daySchedule(): array
    {
        return [
            'start' => self::formatTime(SchoolSettings::get(SchoolSettings::KEY_DAY_START, '07:30')) ?: '07:30',
            'end' => self::formatTime(SchoolSettings::get(SchoolSettings::KEY_DAY_END, '15:40')) ?: '15:40',
            'lesson' => max(20, (int) SchoolSettings::get(SchoolSettings::KEY_LESSON_MINUTES, '50')),
            'gap' => 5,
            'break1' => max(0, (int) SchoolSettings::get(SchoolSettings::KEY_BREAK1_MINUTES, '20')),
            'break2' => max(0, (int) SchoolSettings::get(SchoolSettings::KEY_BREAK2_MINUTES, '45')),
            'break1After' => max(1, (int) SchoolSettings::get(SchoolSettings::KEY_BREAK1_AFTER, '3')),
            'break2After' => max(1, (int) SchoolSettings::get(SchoolSettings::KEY_BREAK2_AFTER, '5')),
        ];
    }

    /**
     * @return list<array{start:string,end:string,label:string,type?:string}>
     */
    public static function generatePeriodsFromSchedule(?array $schedule = null): array
    {
        $s = $schedule ?? self::daySchedule();
        $cursor = $s['start'];
        $periods = [];
        for ($n = 1; $n <= 16; $n++) {
            $end = self::addMinutes($cursor, $s['lesson']);
            if ($end > $s['end']) {
                break;
            }
            $periods[] = [
                'start' => $cursor,
                'end' => $end,
                'label' => self::hourLabel($n),
                'type' => 'lesson',
            ];
            $cursor = $end;
            if ($n === $s['break1After'] && $s['break1'] > 0) {
                $cursor = self::addMinutes($cursor, $s['break1']);
            } elseif ($n === $s['break2After'] && $s['break2'] > 0) {
                $cursor = self::addMinutes($cursor, $s['break2']);
            } else {
                $cursor = self::addMinutes($cursor, $s['gap']);
            }
            if ($cursor >= $s['end']) {
                break;
            }
        }

        return $periods;
    }

    /**
     * @param  list<array{start:string,end:string,label:string,type?:string}>  $periods
     * @return list<array{start:string,end:string,label:string,type?:string}>
     */
    public static function insertBreakRows(array $periods): array
    {
        $s = self::daySchedule();
        $out = [];
        $hourIndex = 0;
        foreach ($periods as $i => $period) {
            if (($period['type'] ?? 'lesson') === 'break') {
                $out[] = $period;
                continue;
            }
            $hourIndex++;
            $period['type'] = $period['type'] ?? 'lesson';
            $out[] = $period;

            $breakNo = null;
            $mins = 0;
            if ($hourIndex === $s['break1After'] && $s['break1'] > 0) {
                $breakNo = 1;
                $mins = $s['break1'];
            } elseif ($hourIndex === $s['break2After'] && $s['break2'] > 0) {
                $breakNo = 2;
                $mins = $s['break2'];
            }
            if (! $breakNo) {
                continue;
            }

            $start = $period['end'];
            $end = self::addMinutes($start, $mins);
            $next = $periods[$i + 1] ?? null;
            if ($next && ($next['type'] ?? 'lesson') !== 'break' && $next['start'] > $start) {
                $end = $next['start'];
            }
            if ($end <= $start) {
                continue;
            }
            $out[] = [
                'start' => $start,
                'end' => $end,
                'label' => $breakNo === 1 ? '1ère pause' : '2e pause',
                'type' => 'break',
            ];
        }

        return $out;
    }

    /**
     * Tranches actives (paramétrage), sinon les heures par défaut.
     *
     * @return list<array{start:string,end:string,label:string}>
     */
    public static function periods(): array
    {
        $configured = self::periodsFromOptions();

        return $configured !== [] ? $configured : self::defaultPeriods();
    }

    /**
     * @return list<array{start:string,end:string,label:string}>
     */
    public static function periodsFromOptions(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('school_options')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $periods = [];
        foreach (SchoolOption::forGroup(SchoolOptionCatalog::GROUP_COURSE_PERIOD) as $row) {
            $parsed = self::parsePeriodValue((string) $row->value);
            if (! $parsed) {
                continue;
            }
            $periods[] = [
                'start' => $parsed['start'],
                'end' => $parsed['end'],
                'label' => (string) ($row->label ?: $parsed['start']),
            ];
        }
        usort($periods, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $periods;
    }

    public static function periodValue(string $start, string $end): string
    {
        return self::formatTime($start).'-'.self::formatTime($end);
    }

    /**
     * @return array{start:string,end:string}|null
     */
    public static function parsePeriodValue(string $value): ?array
    {
        if (! preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', trim($value), $m)) {
            return null;
        }

        return ['start' => $m[1], 'end' => $m[2]];
    }

    public static function hourLabel(int $n): string
    {
        if ($n <= 0) {
            return 'Heure';
        }

        return $n === 1 ? '1ère heure' : $n.'e heure';
    }

    public static function addMinutes(string $hm, int $minutes): string
    {
        $ts = strtotime('1970-01-01 '.$hm);
        if ($ts === false) {
            return self::formatTime($hm);
        }

        return date('H:i', $ts + ($minutes * 60));
    }

    /**
     * @return list<array{start:string,end:string,label:string}>
     */
    public static function defaultPeriods(): array
    {
        return [
            ['start' => '07:30', 'end' => '08:20', 'label' => '1ère heure'],
            ['start' => '08:25', 'end' => '09:15', 'label' => '2e heure'],
            ['start' => '09:20', 'end' => '10:10', 'label' => '3e heure'],
            ['start' => '10:30', 'end' => '11:20', 'label' => '4e heure'],
            ['start' => '11:25', 'end' => '12:15', 'label' => '5e heure'],
            ['start' => '13:00', 'end' => '13:50', 'label' => '6e heure'],
            ['start' => '13:55', 'end' => '14:45', 'label' => '7e heure'],
            ['start' => '14:50', 'end' => '15:40', 'label' => '8e heure'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function palette(): array
    {
        return [
            '#0f766e', '#2563eb', '#7c3aed', '#db2777', '#c2410c',
            '#a16207', '#0891b2', '#4f46e5', '#15803d', '#be123c',
        ];
    }

    public static function colorFor(int $subjectId): string
    {
        $palette = self::palette();

        return $palette[$subjectId % count($palette)];
    }

    public static function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }

    public static function minutesBetween(string $start, string $end): int
    {
        $from = strtotime('1970-01-01 '.$start);
        $to = strtotime('1970-01-01 '.$end);
        if ($from === false || $to === false || $to <= $from) {
            return 0;
        }

        return (int) round(($to - $from) / 60);
    }

    public static function formatTime(?string $time): string
    {
        if (! $time) {
            return '';
        }

        return substr($time, 0, 5);
    }
}
