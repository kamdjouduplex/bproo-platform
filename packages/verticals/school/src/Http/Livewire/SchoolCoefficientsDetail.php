<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolSubject;
use School\Models\SchoolSubjectCoefficient;

class SchoolCoefficientsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $coefficientId;
    public string $mode = 'show';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $subjectId = null;
    public float $coefficient = 1;

    public function mount(int $id): void
    {
        $this->coefficientId = $id;
        SchoolSubjectCoefficient::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $row = $this->entity();
        $this->academicYearId = $row->academic_year_id;
        $this->classId = $row->class_id;
        $this->subjectId = $row->subject_id;
        $this->coefficient = (float) $row->coefficient;
        $this->openEditForm($row->id);
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = $this->classId = $this->subjectId = null;
        $this->coefficient = 1;
    }

    public function save(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'subjectId' => ['required', 'integer', Rule::exists(SchoolSubject::class, 'id')],
            'coefficient' => ['required', 'numeric', 'min:0'],
        ]);
        $this->entity()->update([
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'subject_id' => $this->subjectId,
            'coefficient' => $this->coefficient,
        ]);
        notify()->success('Coefficient mis à jour.');
        $this->cancel();
    }

    public function delete(): void
    {
        $this->entity()->delete();
        notify()->success('Coefficient supprimé.');
        $this->redirect($this->tenantRoute('tenant.school.grading.coefficients.index'));
    }

    protected function entity(): SchoolSubjectCoefficient
    {
        return SchoolSubjectCoefficient::query()
            ->with(['academicYear', 'schoolClass', 'subject'])
            ->findOrFail($this->coefficientId);
    }

    public function render()
    {
        $row = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $subjects = SchoolSubject::query()->where('is_active', true)->orderBy('name')->get();

        return view('school::livewire.school.grading.coefficients-detail', [
            'row' => $row,
            'isManage' => $isManage,
            'years' => $years,
            'classes' => $classes,
            'subjects' => $subjects,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').'Coefficient',
            'subtitle' => $row->subject?->name.' · '.$row->schoolClass?->name,
        ]);
    }
}
