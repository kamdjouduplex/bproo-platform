<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Http\Livewire\Concerns\SearchesStudents;
use School\Models\AcademicYear;
use School\Models\SchoolStudent;
use School\Models\StudentIdCard;
use School\Support\SchoolQrCode;

class SchoolIdCardsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;

    public int $cardId;
    public string $mode = 'show';
    public ?int $academicYearId = null;
    public ?string $batchCode = null;

    public function mount(int $id): void
    {
        $this->cardId = $id;
        StudentIdCard::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $card = $this->entity();
        $this->academicYearId = $card->academic_year_id;
        $this->batchCode = $card->batch_code;
        $this->selectStudent((int) $card->student_id);
        $this->openEditForm($card->id);
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = null;
        $this->batchCode = null;
        $this->clearStudent();
    }

    public function save(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
            'batchCode' => ['nullable', 'string', 'max:120'],
        ]);
        $card = $this->entity();
        $student = SchoolStudent::query()->findOrFail($this->studentId);
        $batch = filled($this->batchCode) ? $this->batchCode : null;
        $card->update([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYearId,
            'batch_code' => $batch,
            'barcode_data' => $student->student_code.($batch ? "-{$batch}" : ''),
            'generated_at' => now(),
        ]);
        if (empty($card->qr_svg)) {
            SchoolQrCode::ensureCached($card);
        }
        notify()->success('Carte ID mise à jour.');
        $this->cancel();
    }

    protected function entity(): StudentIdCard
    {
        return StudentIdCard::query()->with(['student', 'academicYear'])->findOrFail($this->cardId);
    }

    public function render()
    {
        $card = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        return view('school::livewire.school.id-cards.detail', compact('card', 'isManage', 'years') + [
            'studentResults' => $this->studentSearchResults(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').'Carte ID #'.$card->id,
            'subtitle' => $isManage ? 'Actions et modification de la carte ID.' : 'Détail de la carte ID.',
        ]);
    }
}
