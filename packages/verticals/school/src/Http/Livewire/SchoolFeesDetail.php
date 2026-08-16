<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolFeeStructure;

class SchoolFeesDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $feeId;
    public string $mode = 'show';
    public string $name = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public float $amount = 0;
    public string $currencyCode = 'XOF';
    public bool $isActive = true;
    public ?string $description = null;

    public function mount(int $id): void
    {
        $this->feeId = $id;
        SchoolFeeStructure::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $f = $this->entity();
        $this->name = $f->name;
        $this->academicYearId = $f->academic_year_id;
        $this->classId = $f->class_id;
        $this->amount = (float) $f->amount;
        $this->currencyCode = $f->currency_code;
        $this->isActive = (bool) $f->is_active;
        $this->description = $f->description;
        $this->openEditForm($f->id);
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->academicYearId = $this->classId = null;
        $this->amount = 0;
        $this->currencyCode = 'XOF';
        $this->isActive = true;
        $this->description = null;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'academicYearId' => ['nullable', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'amount' => ['required', 'numeric', 'min:0'],
            'currencyCode' => ['required', 'string', 'max:10'],
            'isActive' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $this->entity()->update([
            'name' => $this->name,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'amount' => $this->amount,
            'currency_code' => $this->currencyCode,
            'is_active' => $this->isActive,
            'description' => filled($this->description) ? $this->description : null,
        ]);
        notify()->success('Structure de frais mise à jour.');
        $this->cancel();
    }

    public function activate(): void { $this->entity()->update(['is_active' => true]); notify()->success('Activée.'); }
    public function deactivate(): void { $this->entity()->update(['is_active' => false]); notify()->success('Désactivée.'); }

    protected function entity(): SchoolFeeStructure
    {
        return SchoolFeeStructure::query()->with(['academicYear', 'schoolClass'])->findOrFail($this->feeId);
    }

    public function render()
    {
        $fee = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();

        return view('school::livewire.school.fees.detail', [
            'fee' => $fee,
            'isManage' => $isManage,
            'years' => $years,
            'classes' => $classes,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$fee->name,
            'subtitle' => $isManage ? 'Modifier le barème de frais.' : 'Détail du barème.',
        ]);
    }
}
