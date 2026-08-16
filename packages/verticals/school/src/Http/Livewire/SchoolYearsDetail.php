<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;

class SchoolYearsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $yearId;

    /** @var 'show'|'manage' */
    public string $mode = 'show';

    public string $code = '';

    public string $name = '';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public bool $isActive = false;

    public function mount(int $id): void
    {
        $this->yearId = $id;
        AcademicYear::query()->findOrFail($id);
        $route = request()->route()?->getName() ?? '';
        $this->mode = str_ends_with($route, '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $year = $this->year();
        $this->code = (string) ($year->code ?? '');
        $this->name = $year->name;
        $this->startDate = $year->start_date?->format('Y-m-d');
        $this->endDate = $year->end_date?->format('Y-m-d');
        $this->isActive = (bool) $year->is_active;
        $this->openEditForm($year->id);
    }

    public function activate(): void
    {
        $year = $this->year();
        AcademicYear::query()->where('id', '!=', $year->id)->update(['is_active' => false]);
        $year->update(['is_active' => true]);
        notify()->success('Année activée.');
    }

    public function deactivate(): void
    {
        $this->year()->update(['is_active' => false]);
        notify()->success('Année désactivée.');
    }

    protected function resetFormFields(): void
    {
        $this->code = '';
        $this->name = '';
        $this->startDate = null;
        $this->endDate = null;
        $this->isActive = false;
    }

    public function save(): void
    {
        $this->validate([
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique(AcademicYear::class, 'code')->ignore($this->yearId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'isActive' => ['boolean'],
        ]);

        $year = $this->year();
        $year->update([
            'code' => $this->code !== '' ? $this->code : null,
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_active' => $this->isActive,
        ]);

        if ($year->is_active) {
            AcademicYear::query()->where('id', '!=', $year->id)->update(['is_active' => false]);
        }

        notify()->success('Année académique mise à jour.');
        $this->cancel();
    }

    protected function year(): AcademicYear
    {
        return AcademicYear::query()->findOrFail($this->yearId);
    }

    public function render()
    {
        $year = $this->year();
        $isManage = $this->mode === 'manage';

        return view('school::livewire.school.years.detail', [
            'year' => $year,
            'isManage' => $isManage,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => $isManage ? 'Gérer — '.$year->name : 'Voir — '.$year->name,
            'subtitle' => $isManage ? 'Actions et modification de l’année académique.' : 'Détail de l’année académique.',
        ]);
    }
}
