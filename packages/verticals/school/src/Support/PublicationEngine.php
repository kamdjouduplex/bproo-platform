<?php

namespace School\Support;

use School\Models\SchoolEnrollment;
use School\Models\SchoolExam;
use School\Models\SchoolExamMark;
use School\Models\SchoolPayment;
use School\Models\SchoolPublicationRule;
use School\Models\SchoolResultPublication;
use School\Models\SchoolResultPublicationLine;

/**
 * Configurable result publication — rules decide eligibility (fees, validated marks, director approval).
 */
class PublicationEngine
{
    public function __construct(
        protected GradingCalculator $calculator = new GradingCalculator()
    ) {}

    public function resolveRule(?int $ruleId, int $academicYearId, ?int $classId): ?SchoolPublicationRule
    {
        if ($ruleId) {
            return SchoolPublicationRule::query()->find($ruleId);
        }

        $q = SchoolPublicationRule::query()
            ->where('is_active', true)
            ->where(function ($inner) use ($academicYearId) {
                $inner->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId);
            });

        if ($classId) {
            $specific = (clone $q)->where('class_id', $classId)->orderByDesc('id')->first();
            if ($specific) {
                return $specific;
            }
        }

        return $q->whereNull('class_id')->orderByDesc('id')->first();
    }

    /**
     * @return array{eligible:bool, checks:array<string,bool|null>, blocked:array<int,string>, result:array}
     */
    public function evaluateStudent(int $studentId, int $academicYearId, ?int $classId, ?SchoolPublicationRule $rule): array
    {
        $result = $this->calculator->computeForStudent($studentId, $academicYearId, $classId);
        $checks = [
            'fees_paid' => null,
            'marks_validated' => null,
        ];
        $blocked = [];

        if ($rule?->require_fees_paid) {
            $paid = (float) SchoolPayment::query()
                ->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)
                ->where('status', 'verified')
                ->sum('amount');

            $min = $rule->min_fees_amount !== null ? (float) $rule->min_fees_amount : 0.01;
            $ok = $paid >= $min;
            $checks['fees_paid'] = $ok;
            $checks['fees_amount'] = $paid;
            if (! $ok) {
                $blocked[] = 'Frais insuffisants (payé: '.$paid.', requis: '.$min.').';
            }
        }

        if ($rule?->require_validated_marks) {
            $examIds = SchoolExam::query()
                ->where('academic_year_id', $academicYearId)
                ->when($classId, fn ($q) => $q->where('class_id', $classId))
                ->whereIn('status', ['open', 'closed'])
                ->pluck('id');

            $marks = SchoolExamMark::query()
                ->where('student_id', $studentId)
                ->whereIn('exam_id', $examIds)
                ->get();

            $ok = $marks->isNotEmpty() && $marks->every(fn ($m) => $m->validated_at !== null || $m->is_absent);
            // Also: if no marks at all, not OK when required
            if ($marks->isEmpty()) {
                $ok = false;
            }
            $checks['marks_validated'] = $ok;
            if (! $ok) {
                $blocked[] = 'Notes non validées (ou absentes).';
            }
        }

        $eligible = $blocked === [];

        return [
            'eligible' => $eligible,
            'checks' => $checks,
            'blocked' => $blocked,
            'result' => $result,
        ];
    }

    public function syncLines(SchoolResultPublication $publication): int
    {
        $rule = $this->resolveRule(
            $publication->publication_rule_id,
            (int) $publication->academic_year_id,
            $publication->class_id ? (int) $publication->class_id : null
        );

        $studentIds = SchoolEnrollment::query()
            ->where('academic_year_id', $publication->academic_year_id)
            ->when($publication->class_id, fn ($q) => $q->where('class_id', $publication->class_id))
            ->where('status', 'enrolled')
            ->pluck('student_id');

        $count = 0;
        foreach ($studentIds as $studentId) {
            $eval = $this->evaluateStudent(
                (int) $studentId,
                (int) $publication->academic_year_id,
                $publication->class_id ? (int) $publication->class_id : null,
                $rule
            );

            SchoolResultPublicationLine::query()->updateOrCreate(
                [
                    'publication_id' => $publication->id,
                    'student_id' => $studentId,
                ],
                [
                    'average' => $eval['result']['average'],
                    'grade_label' => $eval['result']['grade_label'],
                    'passed' => (bool) $eval['result']['passed'],
                    'promoted' => (bool) $eval['result']['promoted'],
                    'eligible' => $eval['eligible'],
                    'checks' => $eval['checks'],
                    'blocked_reasons' => $eval['blocked'] !== [] ? implode(' ', $eval['blocked']) : null,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function publishEligible(SchoolResultPublication $publication): int
    {
        $updated = SchoolResultPublicationLine::query()
            ->where('publication_id', $publication->id)
            ->where('eligible', true)
            ->update(['is_published' => true]);

        $publication->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $updated;
    }
}
