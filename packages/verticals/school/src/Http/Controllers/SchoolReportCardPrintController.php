<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\SchoolEnrollment;
use School\Models\SchoolExamMark;
use School\Models\SchoolStudent;
use School\Support\GradingCalculator;
use School\Support\SchoolNotificationDispatcher;

class SchoolReportCardPrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request): View
    {
        $this->authorizeSchoolPermission('school_report_cards.print');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $type = $request->query('type', 'bulletin'); // bulletin | transcript | sheet
        if (! in_array($type, ['bulletin', 'transcript', 'sheet'], true)) {
            $type = 'bulletin';
        }

        $enrollmentIds = array_filter(array_map('intval', (array) $request->query('enrollment_ids', [])));
        $studentId = $request->integer('student') ?: null;
        $yearId = $request->integer('year') ?: null;
        $classId = $request->integer('class') ?: null;

        $query = SchoolEnrollment::query()
            ->with(['student', 'academicYear', 'schoolClass'])
            ->where('status', 'enrolled');

        if ($enrollmentIds !== []) {
            $query->whereIn('id', $enrollmentIds);
        } elseif ($studentId && $yearId) {
            $query->where('student_id', $studentId)->where('academic_year_id', $yearId);
        } elseif ($yearId) {
            $query->where('academic_year_id', $yearId);
            if ($classId) {
                $query->where('class_id', $classId);
            }
        } else {
            abort(404, 'Aucun élève à imprimer.');
        }

        $enrollments = $query->orderBy('id')->get();
        if ($enrollments->isEmpty()) {
            abort(404, 'Aucune inscription trouvée.');
        }

        $calculator = app(GradingCalculator::class);
        $cards = [];
        foreach ($enrollments as $enrollment) {
            $result = $calculator->computeForStudent(
                (int) $enrollment->student_id,
                (int) $enrollment->academic_year_id,
                $enrollment->class_id ? (int) $enrollment->class_id : null
            );

            $history = [];
            if ($type === 'transcript') {
                $history = SchoolEnrollment::query()
                    ->with(['academicYear', 'schoolClass'])
                    ->where('student_id', $enrollment->student_id)
                    ->orderBy('academic_year_id')
                    ->get()
                    ->map(function ($e) use ($calculator) {
                        $r = $calculator->computeForStudent(
                            (int) $e->student_id,
                            (int) $e->academic_year_id,
                            $e->class_id ? (int) $e->class_id : null
                        );

                        return [
                            'year' => $e->academicYear?->name,
                            'class' => $e->schoolClass?->name,
                            'average' => $r['average'],
                            'grade_label' => $r['grade_label'],
                            'passed' => $r['passed'],
                            'promoted' => $r['promoted'],
                        ];
                    })
                    ->all();
            }

            $marksDetail = [];
            if ($type === 'sheet') {
                $marksDetail = SchoolExamMark::query()
                    ->with(['exam.subject'])
                    ->where('student_id', $enrollment->student_id)
                    ->whereHas('exam', fn ($q) => $q->where('academic_year_id', $enrollment->academic_year_id))
                    ->orderBy('exam_id')
                    ->get();
            }

            $cards[] = [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'result' => $result,
                'history' => $history,
                'marks' => $marksDetail,
            ];
        }

        if ($request->boolean('notify')) {
            $dispatcher = app(SchoolNotificationDispatcher::class);
            foreach ($cards as $card) {
                /** @var SchoolStudent|null $student */
                $student = $card['student'];
                if ($student) {
                    $dispatcher->dispatch('report_card', $student, [
                        'year' => $card['enrollment']->academicYear?->name,
                        'class' => $card['enrollment']->schoolClass?->name,
                        'average' => $card['result']['average'] ?? '',
                        'mention' => $card['result']['grade_label'] ?? '',
                    ]);
                }
            }
        }

        $label = match ($type) {
            'transcript' => 'releve',
            'sheet' => 'feuille-notes',
            default => 'bulletin',
        };

        $ref = $enrollments->count() === 1
            ? ($enrollments->first()->student?->student_code ?? 'doc')
            : 'lot-'.$enrollments->count();

        return view('school::print.report-card', array_merge([
            'type' => $type,
            'cards' => $cards,
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
            'logoSrc' => $settings['logo_embed_src'] ?? $settings['logo_url'] ?? null,
        ], PrintDocument::context(
            $request,
            $label,
            $ref,
            'tenant.school.report_cards.index'
        )));
    }
}
