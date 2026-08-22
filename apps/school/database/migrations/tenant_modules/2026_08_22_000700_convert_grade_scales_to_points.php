<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('school_grade_scales') || ! $schema->hasTable('school_grading_systems')) {
            return;
        }

        $systems = DB::connection('tenant')->table('school_grading_systems')->get();
        foreach ($systems as $system) {
            $base = (float) ($system->scale_base ?: 20);
            $scales = DB::connection('tenant')->table('school_grade_scales')
                ->where('grading_system_id', $system->id)
                ->get();

            $maxStored = $scales->max('max_percent');
            if ($maxStored === null || (float) $maxStored <= $base + 0.001) {
                continue;
            }

            foreach ($scales as $scale) {
                $min = $this->toPoints((float) $scale->min_percent, $base, false);
                $max = $this->toPoints((float) $scale->max_percent, $base, true);
                DB::connection('tenant')->table('school_grade_scales')
                    ->where('id', $scale->id)
                    ->update([
                        'min_percent' => $min,
                        'max_percent' => $max,
                    ]);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('school_grade_scales') || ! $schema->hasTable('school_grading_systems')) {
            return;
        }

        $systems = DB::connection('tenant')->table('school_grading_systems')->get();
        foreach ($systems as $system) {
            $base = (float) ($system->scale_base ?: 20);
            if ($base <= 0) {
                continue;
            }
            $scales = DB::connection('tenant')->table('school_grade_scales')
                ->where('grading_system_id', $system->id)
                ->get();

            $maxStored = $scales->max('max_percent');
            if ($maxStored === null || (float) $maxStored > $base + 0.001) {
                continue;
            }

            foreach ($scales as $scale) {
                DB::connection('tenant')->table('school_grade_scales')
                    ->where('id', $scale->id)
                    ->update([
                        'min_percent' => round(((float) $scale->min_percent / $base) * 100, 2),
                        'max_percent' => round(((float) $scale->max_percent / $base) * 100, 2),
                    ]);
            }
        }
    }

    protected function toPoints(float $percent, float $base, bool $isMax): float
    {
        if ($percent >= 99.995) {
            return $base;
        }
        if ($percent <= 0) {
            return 0.0;
        }
        $raw = $percent * $base / 100;
        if ($isMax) {
            return floor($raw * 100 + 1e-6) / 100;
        }

        return round($raw, 2);
    }
};
