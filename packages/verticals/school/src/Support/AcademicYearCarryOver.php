<?php

namespace School\Support;

use School\Models\AcademicYear;
use School\Models\SchoolExam;
use School\Models\SchoolFeeStructure;
use School\Models\SchoolGradingRule;
use School\Models\SchoolSubjectCoefficient;

/**
 * Carry over year-scoped bindings into a new academic year.
 * Does NOT duplicate masters (classes, subjects, teachers, grading systems).
 */
class AcademicYearCarryOver
{
    /**
     * @param  array{fees?:bool,grading?:bool,coefficients?:bool,exams?:bool}  $options
     * @return array{fees:int,grading:int,coefficients:int,exams:int}
     */
    public function fromYear(AcademicYear $source, AcademicYear $target, array $options = []): array
    {
        $counts = ['fees' => 0, 'grading' => 0, 'coefficients' => 0, 'exams' => 0];

        if ($options['fees'] ?? false) {
            $rows = SchoolFeeStructure::query()
                ->where('academic_year_id', $source->id)
                ->get();
            foreach ($rows as $row) {
                SchoolFeeStructure::query()->create([
                    'name' => $row->name,
                    'academic_year_id' => $target->id,
                    'class_id' => $row->class_id,
                    'amount' => $row->amount,
                    'currency_code' => $row->currency_code,
                    'is_active' => $row->is_active,
                    'description' => $row->description,
                ]);
                $counts['fees']++;
            }
        }

        if ($options['grading'] ?? false) {
            $rows = SchoolGradingRule::query()
                ->where('academic_year_id', $source->id)
                ->get();
            foreach ($rows as $row) {
                SchoolGradingRule::query()->create([
                    'academic_year_id' => $target->id,
                    'class_id' => $row->class_id,
                    'grading_system_id' => $row->grading_system_id,
                    'pass_mark' => $row->pass_mark,
                    'promotion_average' => $row->promotion_average,
                    'max_failed_subjects' => $row->max_failed_subjects,
                    'ranking_method' => $row->ranking_method,
                    'absent_counts_as_zero' => $row->absent_counts_as_zero,
                    'require_validated_marks' => $row->require_validated_marks,
                    'is_active' => $row->is_active,
                ]);
                $counts['grading']++;
            }
        }

        if ($options['coefficients'] ?? false) {
            $rows = SchoolSubjectCoefficient::query()
                ->where('academic_year_id', $source->id)
                ->get();
            foreach ($rows as $row) {
                SchoolSubjectCoefficient::query()->create([
                    'academic_year_id' => $target->id,
                    'class_id' => $row->class_id,
                    'subject_id' => $row->subject_id,
                    'coefficient' => $row->coefficient,
                ]);
                $counts['coefficients']++;
            }
        }

        if ($options['exams'] ?? false) {
            $rows = SchoolExam::query()
                ->where('academic_year_id', $source->id)
                ->get();
            foreach ($rows as $row) {
                SchoolExam::query()->create([
                    'academic_year_id' => $target->id,
                    'class_id' => $row->class_id,
                    'subject_id' => $row->subject_id,
                    'teacher_id' => $row->teacher_id,
                    'title' => $row->title,
                    'kind' => $row->kind,
                    'period' => $row->period,
                    'exam_date' => null,
                    'max_score' => $row->max_score,
                    'coefficient' => $row->coefficient,
                    'status' => 'draft',
                    'notes' => $row->notes,
                ]);
                $counts['exams']++;
            }
        }

        return $counts;
    }
}
