<?php

namespace School\Support;

use School\Models\SchoolTeacher;

final class TeacherCodeGenerator
{
    public static function next(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $prefix = self::prefixForYear($year);

        $latest = SchoolTeacher::query()
            ->where('teacher_code', 'like', $prefix.'%')
            ->orderByDesc('teacher_code')
            ->value('teacher_code');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return self::format($seq, $year);
    }

    public static function format(int $sequence, ?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $sep = SchoolSettings::get(SchoolSettings::KEY_ID_SEPARATOR, '-');
        $padding = max(3, min(8, (int) SchoolSettings::get(SchoolSettings::KEY_ID_SEQ_PADDING, '4')));

        return 'ENS'.$sep.$year.$sep.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }

    protected static function prefixForYear(int $year): string
    {
        $sep = SchoolSettings::get(SchoolSettings::KEY_ID_SEPARATOR, '-');

        return 'ENS'.$sep.$year.$sep;
    }
}
