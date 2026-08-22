<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolExam;
use School\Models\SchoolExamMark;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Support\SchoolExamCatalog;
use School\Support\SchoolOptionCatalog;

class SchoolExamsDetail extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $examId;
    public string $mode = 'show';

    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $subjectId = null;
    public ?int $teacherId = null;
    public string $title = '';
    public string $kind = 'devoir';
    public string $period = 'seq_1';
    public string $lastSuggestedTitle = '';
    public ?string $examDate = null;
    public float $maxScore = 20;
    public float $coefficient = 1;
    public string $status = 'draft';
    public ?string $notes = null;

    /** @var array<int, array{score:?string,is_absent:bool,remarks:string}> */
    public array $markRows = [];

    public function mount(int $id): void
    {
        $this->examId = $id;
        SchoolExam::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
        try {
            SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
        }
        $this->loadMarkRows();
    }

    public function edit(): void
    {
        $exam = $this->entity();
        $this->academicYearId = $exam->academic_year_id;
        $this->classId = $exam->class_id;
        $this->subjectId = $exam->subject_id;
        $this->teacherId = $exam->teacher_id;
        $this->title = $exam->title;
        $this->kind = (string) ($exam->kind ?: 'devoir');
        $this->period = (string) ($exam->period ?: 'seq_1');
        $this->lastSuggestedTitle = SchoolExamCatalog::suggestTitle($this->kind, $this->period);
        $this->examDate = $exam->exam_date?->format('Y-m-d');
        $this->maxScore = (float) $exam->max_score;
        $this->coefficient = (float) $exam->coefficient;
        $this->status = $exam->status;
        $this->notes = $exam->notes;
        $this->openEditForm($exam->id);
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = $this->classId = $this->subjectId = $this->teacherId = null;
        $this->title = '';
        $this->kind = 'devoir';
        $this->period = 'seq_1';
        $this->lastSuggestedTitle = '';
        $this->examDate = null;
        $this->maxScore = 20;
        $this->coefficient = 1;
        $this->status = 'draft';
        $this->notes = null;
    }

    public function updatedKind(): void
    {
        $this->suggestTitleIfEmpty();
    }

    public function updatedPeriod(): void
    {
        $this->suggestTitleIfEmpty();
    }

    protected function suggestTitleIfEmpty(): void
    {
        $suggested = SchoolExamCatalog::suggestTitle($this->kind, $this->period);
        if (trim($this->title) === '' || $this->title === $this->lastSuggestedTitle) {
            $this->title = $suggested;
        }
        $this->lastSuggestedTitle = $suggested;
    }

    public function save(): void
    {
        $exam = $this->entity();
        if (! $exam->isEditable() && $this->status === 'closed') {
            // allow editing notes? block structural edits when already closed unless reopening
        }

        $kindValues = SchoolExamCatalog::kindValues();
        $periodValues = SchoolExamCatalog::periodValues();

        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'subjectId' => ['required', 'integer', Rule::exists(SchoolSubject::class, 'id')],
            'teacherId' => ['nullable', 'integer', Rule::exists(SchoolTeacher::class, 'id')],
            'kind' => ['required', 'string', Rule::in($kindValues !== [] ? $kindValues : [$this->kind])],
            'period' => ['required', 'string', Rule::in($periodValues !== [] ? $periodValues : [$this->period])],
            'title' => ['required', 'string', 'max:255'],
            'examDate' => ['nullable', 'date'],
            'maxScore' => ['required', 'numeric', 'min:0.01'],
            'coefficient' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,open,closed'],
            'notes' => ['nullable', 'string'],
        ]);

        $exam->update([
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'subject_id' => $this->subjectId,
            'teacher_id' => $this->teacherId,
            'title' => $this->title,
            'kind' => $this->kind,
            'period' => $this->period,
            'exam_date' => $this->examDate,
            'max_score' => $this->maxScore,
            'coefficient' => $this->coefficient,
            'status' => $this->status,
            'notes' => filled($this->notes) ? $this->notes : null,
        ]);

        notify()->success('Examen mis à jour.');
        $this->cancel();
        $this->loadMarkRows();
    }

    public function openExam(): void
    {
        $this->entity()->update(['status' => 'open']);
        notify()->success('Examen ouvert pour la saisie des notes.');
    }

    public function closeExam(): void
    {
        $this->entity()->update(['status' => 'closed']);
        notify()->success('Examen clôturé.');
    }

    public function syncRoster(): void
    {
        $exam = $this->entity();
        $studentIds = SchoolEnrollment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('class_id', $exam->class_id)
            ->where('status', 'enrolled')
            ->pluck('student_id');

        $count = 0;
        foreach ($studentIds as $studentId) {
            $created = SchoolExamMark::query()->firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $studentId],
                ['score' => null, 'is_absent' => false]
            );
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->loadMarkRows();
        notify()->success($count > 0
            ? "{$count} élève(s) ajouté(s) à la feuille de notes."
            : 'Feuille de notes à jour.');
    }

    public function saveMarks(): void
    {
        if (! $this->canSchool('school_exams.marks') && ! $this->canSchool('school_exams.manage')) {
            notify()->error('Permission refusée pour cette action.');

            return;
        }

        $exam = $this->entity();
        if ($exam->status === 'closed') {
            notify()->error('Examen clôturé — notes non modifiables.');

            return;
        }

        $max = (float) $exam->max_score;

        DB::connection('tenant')->transaction(function () use ($exam, $max) {
            foreach ($this->markRows as $studentId => $row) {
                $studentId = (int) $studentId;
                $absent = (bool) ($row['is_absent'] ?? false);
                $scoreRaw = $row['score'] ?? null;
                $score = ($scoreRaw === '' || $scoreRaw === null) ? null : (float) $scoreRaw;

                if (! $absent && $score !== null && ($score < 0 || $score > $max)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "markRows.{$studentId}.score" => "Note hors barème (0 – {$max}).",
                    ]);
                }

                SchoolExamMark::query()->updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $studentId],
                    [
                        'score' => $absent ? null : $score,
                        'is_absent' => $absent,
                        'remarks' => filled($row['remarks'] ?? null) ? (string) $row['remarks'] : null,
                    ]
                );
            }
        });

        if ($exam->status === 'draft') {
            $exam->update(['status' => 'open']);
        }

        $this->loadMarkRows();
        notify()->success('Notes enregistrées.');
    }

    public function validateMarks(): void
    {
        $exam = $this->entity();
        SchoolExamMark::query()
            ->where('exam_id', $exam->id)
            ->whereNull('validated_at')
            ->update(['validated_at' => now()]);

        $this->loadMarkRows();
        notify()->success('Notes validées.');
    }

    protected function loadMarkRows(): void
    {
        $exam = $this->entity();
        $marks = SchoolExamMark::query()
            ->with('student')
            ->where('exam_id', $exam->id)
            ->get()
            ->keyBy('student_id');

        $studentIds = SchoolEnrollment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('class_id', $exam->class_id)
            ->pluck('student_id')
            ->merge($marks->keys())
            ->unique()
            ->values();

        $students = \School\Models\SchoolStudent::query()
            ->whereIn('id', $studentIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $rows = [];
        foreach ($students as $student) {
            $mark = $marks->get($student->id);
            $rows[$student->id] = [
                'score' => $mark && $mark->score !== null ? (string) $mark->score : '',
                'is_absent' => (bool) ($mark?->is_absent ?? false),
                'remarks' => (string) ($mark?->remarks ?? ''),
                'validated_at' => $mark?->validated_at?->format('d/m/Y H:i'),
                'label' => $student->student_code.' — '.$student->full_name,
            ];
        }

        $this->markRows = $rows;
    }

    protected function entity(): SchoolExam
    {
        return SchoolExam::query()
            ->with(['academicYear', 'schoolClass', 'subject', 'teacher', 'marks.student'])
            ->findOrFail($this->examId);
    }

    public function render()
    {
        $exam = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $subjects = SchoolSubject::query()->where('is_active', true)->orderBy('name')->get();
        $teachers = SchoolTeacher::query()->where('is_active', true)->orderBy('full_name')->get();

        $statusLabels = [
            'draft' => 'Brouillon',
            'open' => 'Ouvert',
            'closed' => 'Clôturé',
        ];

        return view('school::livewire.school.exams.detail', [
            'exam' => $exam,
            'isManage' => $isManage,
            'years' => $years,
            'classes' => $classes,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'statusLabels' => $statusLabels,
            'examKinds' => SchoolExamCatalog::kinds(),
            'examPeriods' => SchoolExamCatalog::periods(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$exam->title,
            'subtitle' => $isManage ? 'Modifier l’épreuve et saisir les notes.' : 'Détail de l’épreuve et des notes.',
        ]);
    }
}
