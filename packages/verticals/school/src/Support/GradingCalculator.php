<?php

namespace School\Support;

use School\Models\SchoolEnrollment;
use School\Models\SchoolExam;
use School\Models\SchoolExamMark;
use School\Models\SchoolGradeScale;
use School\Models\SchoolGradingRule;
use School\Models\SchoolGradingSystem;
use School\Models\SchoolSubjectCoefficient;

/**
 * Configurable result calculation — no hardcoded averages / pass logic.
 */
class GradingCalculator
{
    /**
     * @return array{
     *   scale_base: float,
     *   subjects: array<int, array{subject_id:int, subject_name:?string, average:?float, coefficient:float, weighted:?float, passed:?bool, exams:array}>,
     *   average:?float,
     *   grade_label:?string,
     *   passed:bool,
     *   promoted:bool,
     *   failed_subjects:int,
     *   ranking_method:string,
     *   rule:?SchoolGradingRule,
     *   system:?SchoolGradingSystem
     * }
     */
    public function computeForStudent(int $studentId, int $academicYearId, ?int $classId = null): array
    {
        if ($classId === null) {
            $classId = SchoolEnrollment::query()
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->value('class_id');
        }

        $rule = $this->resolveRule($academicYearId, $classId ? (int) $classId : null);
        $system = $rule?->gradingSystem
            ?? SchoolGradingSystem::query()->where('is_active', true)->orderBy('id')->first();

        $scaleBase = (float) ($system?->scale_base ?? 20);
        $rankingMethod = $rule?->ranking_method ?? 'weighted_average';
        $absentAsZero = (bool) ($rule?->absent_counts_as_zero ?? true);
        $requireValidated = (bool) ($rule?->require_validated_marks ?? false);
        $passMark = (float) ($rule?->pass_mark ?? ($scaleBase / 2));
        $promotionAverage = (float) ($rule?->promotion_average ?? $passMark);
        $maxFailed = $rule?->max_failed_subjects;

        $examsQuery = SchoolExam::query()
            ->with('subject')
            ->where('academic_year_id', $academicYearId)
            ->whereIn('status', ['open', 'closed']);

        if ($classId) {
            $examsQuery->where('class_id', $classId);
        }

        $exams = $examsQuery->get();
        $examIds = $exams->pluck('id');

        $marks = SchoolExamMark::query()
            ->where('student_id', $studentId)
            ->whereIn('exam_id', $examIds)
            ->get()
            ->keyBy('exam_id');

        $coeffs = SchoolSubjectCoefficient::query()
            ->where('academic_year_id', $academicYearId)
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->get()
            ->keyBy('subject_id');

        $bySubject = [];
        foreach ($exams as $exam) {
            $mark = $marks->get($exam->id);
            if ($requireValidated && $mark && $mark->validated_at === null) {
                continue;
            }

            $sid = (int) $exam->subject_id;
            if (! isset($bySubject[$sid])) {
                $bySubject[$sid] = [
                    'subject_id' => $sid,
                    'subject_name' => $exam->subject?->name,
                    'exam_scores' => [],
                    'exam_weights' => [],
                    'exams' => [],
                ];
            }

            $score = null;
            if ($mark) {
                if ($mark->is_absent) {
                    $score = $absentAsZero ? 0.0 : null;
                } elseif ($mark->score !== null) {
                    // Normalize exam score to scale_base
                    $max = (float) $exam->max_score ?: $scaleBase;
                    $score = $max > 0 ? ((float) $mark->score / $max) * $scaleBase : null;
                }
            }

            $weight = (float) ($exam->coefficient ?: 1);
            $bySubject[$sid]['exams'][] = [
                'exam_id' => $exam->id,
                'title' => $exam->title,
                'raw_score' => $mark?->score,
                'is_absent' => (bool) ($mark?->is_absent),
                'normalized_score' => $score,
                'weight' => $weight,
            ];

            if ($score !== null) {
                $bySubject[$sid]['exam_scores'][] = $score;
                $bySubject[$sid]['exam_weights'][] = $weight;
            }
        }

        $subjectsOut = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $simpleSum = 0.0;
        $simpleCount = 0;
        $failedSubjects = 0;

        foreach ($bySubject as $sid => $block) {
            $subjectAvg = $this->weightedMean($block['exam_scores'], $block['exam_weights']);
            $coeff = (float) ($coeffs->get($sid)?->coefficient ?? 1);
            $passed = $subjectAvg === null ? null : ($subjectAvg >= $passMark);
            if ($passed === false) {
                $failedSubjects++;
            }

            $weighted = $subjectAvg !== null ? $subjectAvg * $coeff : null;
            if ($weighted !== null) {
                $weightedSum += $weighted;
                $weightTotal += $coeff;
            }
            if ($subjectAvg !== null) {
                $simpleSum += $subjectAvg;
                $simpleCount++;
            }

            $subjectsOut[] = [
                'subject_id' => $sid,
                'subject_name' => $block['subject_name'],
                'average' => $subjectAvg,
                'coefficient' => $coeff,
                'weighted' => $weighted,
                'passed' => $passed,
                'exams' => $block['exams'],
            ];
        }

        $average = null;
        if ($rankingMethod === 'simple_average') {
            $average = $simpleCount > 0 ? $simpleSum / $simpleCount : null;
        } else {
            $average = $weightTotal > 0 ? $weightedSum / $weightTotal : null;
        }

        $gradeLabel = $average !== null
            ? $this->resolveGradeLabel($system, ($average / $scaleBase) * 100)
            : null;

        $passedOverall = $average !== null && $average >= $passMark;
        if ($maxFailed !== null && $failedSubjects > $maxFailed) {
            $passedOverall = false;
        }

        $promoted = $average !== null && $average >= $promotionAverage;
        if ($maxFailed !== null && $failedSubjects > $maxFailed) {
            $promoted = false;
        }

        return [
            'scale_base' => $scaleBase,
            'subjects' => $subjectsOut,
            'average' => $average,
            'grade_label' => $gradeLabel,
            'passed' => $passedOverall,
            'promoted' => $promoted,
            'failed_subjects' => $failedSubjects,
            'ranking_method' => $rankingMethod,
            'rule' => $rule,
            'system' => $system,
        ];
    }

    public function resolveRule(int $academicYearId, ?int $classId): ?SchoolGradingRule
    {
        $query = SchoolGradingRule::query()
            ->with('gradingSystem.scales')
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true);

        if ($classId) {
            $specific = (clone $query)->where('class_id', $classId)->first();
            if ($specific) {
                return $specific;
            }
        }

        return $query->whereNull('class_id')->first();
    }

    public function resolveGradeLabel(?SchoolGradingSystem $system, float $percent): ?string
    {
        if (! $system) {
            return null;
        }

        $scales = $system->relationLoaded('scales')
            ? $system->scales
            : $system->scales()->get();

        foreach ($scales as $scale) {
            /** @var SchoolGradeScale $scale */
            if ($percent >= (float) $scale->min_percent && $percent <= (float) $scale->max_percent) {
                return $scale->label;
            }
        }

        return null;
    }

    /**
     * @param  array<int, float>  $scores
     * @param  array<int, float>  $weights
     */
    protected function weightedMean(array $scores, array $weights): ?float
    {
        if ($scores === []) {
            return null;
        }

        $sum = 0.0;
        $wSum = 0.0;
        foreach ($scores as $i => $score) {
            $w = (float) ($weights[$i] ?? 1);
            $sum += $score * $w;
            $wSum += $w;
        }

        return $wSum > 0 ? $sum / $wSum : null;
    }
}
