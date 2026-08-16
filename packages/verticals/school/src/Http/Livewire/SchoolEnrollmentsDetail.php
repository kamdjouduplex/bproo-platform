<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Http\Livewire\Concerns\SearchesStudents;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Support\SchoolOptionCatalog;

class SchoolEnrollmentsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;

    public int $enrollmentId;
    public string $mode = 'show';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?string $section = null;
    public string $status = 'enrolled';

    public function mount(int $id): void
    {
        $this->enrollmentId = $id;
        SchoolEnrollment::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $enrollment = $this->entity();
        $this->academicYearId = $enrollment->academic_year_id;
        $this->classId = $enrollment->class_id;
        $this->section = $enrollment->section;
        $this->status = $enrollment->status;
        $this->selectStudent((int) $enrollment->student_id);
        $this->openEditForm($enrollment->id);
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = $this->classId = null;
        $this->section = null;
        $this->status = 'enrolled';
        $this->clearStudent();
    }

    public function save(): void
    {
        $sections = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION)->pluck('value')->all();
        $statuses = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS)->pluck('value')->all();
        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
            'section' => ['nullable', 'string', 'max:80', Rule::in(array_merge([''], $sections))],
            'status' => ['required', 'string', 'max:80', Rule::in($statuses !== [] ? $statuses : [$this->status])],
        ]);
        $this->entity()->update([
            'student_id' => $this->studentId, 'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId, 'section' => filled($this->section) ? $this->section : null,
            'status' => $this->status,
        ]);

        app(\School\Support\StudentLedgerService::class)->chargeFeesForEnrollment(
            (int) $this->studentId,
            (int) $this->academicYearId,
            $this->classId ? (int) $this->classId : null
        );

        notify()->success('Inscription mise à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolEnrollment
    {
        return SchoolEnrollment::query()->with(['student', 'academicYear', 'schoolClass'])->findOrFail($this->enrollmentId);
    }

    public function render()
    {
        $enrollment = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $sections = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION);
        $statuses = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS);
        $statusLabel = $statuses->firstWhere('value', $enrollment->status)?->label ?? $enrollment->status;
        $sectionLabel = $sections->firstWhere('value', $enrollment->section)?->label ?? $enrollment->section;
        return view('school::livewire.school.enrollments.detail', compact(
            'enrollment', 'isManage', 'years', 'classes', 'sections', 'statuses', 'statusLabel', 'sectionLabel'
        ) + ['studentResults' => $this->studentSearchResults(), 'tenantCode' => $this->tenantCode()])
            ->layout('layouts.app', [
                'title' => ($isManage ? 'Gérer — ' : 'Voir — ').'Inscription #'.$enrollment->id,
                'subtitle' => $isManage ? 'Actions et modification de l’inscription.' : 'Détail de l’inscription.',
            ]);
    }
}
