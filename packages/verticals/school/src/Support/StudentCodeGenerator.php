<?php

namespace School\Support;

use School\Models\SchoolStudent;

final class StudentCodeGenerator
{
    public static function next(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $prefix = self::prefixForYear($year);
        $padding = self::padding();

        $latest = SchoolStudent::query()
            ->where('student_code', 'like', $prefix.'%')
            ->orderByDesc('student_code')
            ->value('student_code');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return self::format($seq, $year, $padding);
    }

    public static function format(int $sequence, ?int $year = null, ?int $padding = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $padding = $padding ?: self::padding();
        $sep = SchoolSettings::get(SchoolSettings::KEY_ID_SEPARATOR, '-');
        $parts = [];

        $prefix = trim(SchoolSettings::get(SchoolSettings::KEY_ID_PREFIX, 'SCH'));
        if ($prefix !== '') {
            $parts[] = $prefix;
        }

        $yearPart = self::yearPart($year);
        if ($yearPart !== '') {
            $parts[] = $yearPart;
        }

        $parts[] = str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

        return implode($sep, $parts);
    }

    protected static function prefixForYear(int $year): string
    {
        $sep = SchoolSettings::get(SchoolSettings::KEY_ID_SEPARATOR, '-');
        $prefix = trim(SchoolSettings::get(SchoolSettings::KEY_ID_PREFIX, 'SCH'));
        $yearPart = self::yearPart($year);

        $stem = $prefix;
        if ($yearPart !== '') {
            $stem = $prefix === '' ? $yearPart : $prefix.$sep.$yearPart;
        }

        return $stem === '' ? '' : $stem.$sep;
    }

    protected static function yearPart(int $year): string
    {
        $format = SchoolSettings::get(SchoolSettings::KEY_ID_YEAR_FORMAT, 'yyyy');

        return match ($format) {
            'yy' => substr((string) $year, -2),
            'none' => '',
            default => (string) $year,
        };
    }

    protected static function padding(): int
    {
        $padding = (int) SchoolSettings::get(SchoolSettings::KEY_ID_SEQ_PADDING, '4');

        return max(3, min(8, $padding));
    }
}
