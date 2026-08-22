<?php

namespace School\Support;

use Illuminate\Support\Facades\Schema;
use School\Models\SchoolSetting;

class SchoolSettings
{
    public const KEY_ID_PREFIX = 'student_id_prefix';
    public const KEY_ID_YEAR_FORMAT = 'student_id_year_format';
    public const KEY_ID_SEQ_PADDING = 'student_id_seq_padding';
    public const KEY_ID_SEPARATOR = 'student_id_separator';
    public const KEY_MINISTRY_SCHOOL_CODE = 'ministry_school_code';
    public const KEY_DAY_START = 'timetable_day_start';
    public const KEY_DAY_END = 'timetable_day_end';
    public const KEY_LESSON_MINUTES = 'timetable_lesson_minutes';
    public const KEY_BREAK1_MINUTES = 'timetable_break1_minutes';
    public const KEY_BREAK2_MINUTES = 'timetable_break2_minutes';
    public const KEY_BREAK1_AFTER = 'timetable_break1_after';
    public const KEY_BREAK2_AFTER = 'timetable_break2_after';

    public static function defaults(): array
    {
        return [
            self::KEY_ID_PREFIX => 'SCH',
            self::KEY_ID_YEAR_FORMAT => 'yyyy', // yyyy | yy | none
            self::KEY_ID_SEQ_PADDING => '4',
            self::KEY_ID_SEPARATOR => '-',
            self::KEY_MINISTRY_SCHOOL_CODE => '',
            self::KEY_DAY_START => '07:30',
            self::KEY_DAY_END => '15:40',
            self::KEY_LESSON_MINUTES => '50',
            self::KEY_BREAK1_MINUTES => '20',
            self::KEY_BREAK2_MINUTES => '45',
            self::KEY_BREAK1_AFTER => '3',
            self::KEY_BREAK2_AFTER => '5',
        ];
    }

    public static function get(string $key, ?string $default = null): string
    {
        if (! self::tableReady()) {
            return $default ?? (self::defaults()[$key] ?? '');
        }

        $row = SchoolSetting::query()->where('key', $key)->first();
        if ($row && $row->value !== null && $row->value !== '') {
            return (string) $row->value;
        }

        return $default ?? (self::defaults()[$key] ?? '');
    }

    public static function set(string $key, ?string $value): void
    {
        if (! self::tableReady()) {
            return;
        }

        SchoolSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function idPatternPreview(?int $year = null, int $sequence = 1): string
    {
        return StudentCodeGenerator::format($sequence, $year);
    }

    protected static function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
