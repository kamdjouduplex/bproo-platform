<?php

namespace School\Support;

use School\Models\SchoolStudent;

final class StudentCodeGenerator
{
    /**
     * Pattern: SCH-{YYYY}-{####} (e.g. SCH-2026-0001)
     */
    public static function next(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $prefix = sprintf('SCH-%d-', $year);

        $latest = SchoolStudent::query()
            ->where('student_code', 'like', $prefix.'%')
            ->orderByDesc('student_code')
            ->value('student_code');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
