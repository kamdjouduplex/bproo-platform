<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolGradeScale;
use School\Models\SchoolGradingSystem;

class SchoolGradingSystemsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $systemId;
    public string $mode = 'show';

    public string $code = '';
    public string $name = '';
    public float $scaleBase = 20;
    public ?string $description = null;
    public bool $isActive = true;

    public bool $showScaleForm = false;
    public ?int $editingScaleId = null;
    public string $scaleLabel = '';
    public float $scaleMin = 0;
    public float $scaleMax = 100;
    public bool $scaleIsPass = true;
    public int $scaleSort = 100;

    public function mount(int $id): void
    {
        $this->systemId = $id;
        SchoolGradingSystem::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $s = $this->entity();
        $this->code = (string) ($s->code ?? '');
        $this->name = $s->name;
        $this->scaleBase = (float) $s->scale_base;
        $this->description = $s->description;
        $this->isActive = (bool) $s->is_active;
        $this->openEditForm($s->id);
    }

    protected function resetFormFields(): void
    {
        $this->code = '';
        $this->name = '';
        $this->scaleBase = 20;
        $this->description = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'scaleBase' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);
        $this->entity()->update([
            'code' => filled($this->code) ? $this->code : null,
            'name' => $this->name,
            'scale_base' => $this->scaleBase,
            'description' => filled($this->description) ? $this->description : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Système mis à jour.');
        $this->cancel();
    }

    public function activate(): void { $this->entity()->update(['is_active' => true]); notify()->success('Système activé.'); }
    public function deactivate(): void { $this->entity()->update(['is_active' => false]); notify()->success('Système désactivé.'); }

    public function createScale(): void
    {
        $this->editingScaleId = null;
        $this->scaleLabel = '';
        $this->scaleMin = 0;
        $this->scaleMax = 100;
        $this->scaleIsPass = true;
        $this->scaleSort = 100;
        $this->showScaleForm = true;
        $this->resetErrorBag();
    }

    public function editScale(int $id): void
    {
        $scale = SchoolGradeScale::query()->where('grading_system_id', $this->systemId)->findOrFail($id);
        $this->editingScaleId = $scale->id;
        $this->scaleLabel = $scale->label;
        $this->scaleMin = (float) $scale->min_percent;
        $this->scaleMax = (float) $scale->max_percent;
        $this->scaleIsPass = (bool) $scale->is_pass;
        $this->scaleSort = (int) $scale->sort_order;
        $this->showScaleForm = true;
        $this->resetErrorBag();
    }

    public function cancelScale(): void
    {
        $this->showScaleForm = false;
        $this->editingScaleId = null;
    }

    public function saveScale(): void
    {
        $this->validate([
            'scaleLabel' => ['required', 'string', 'max:80'],
            'scaleMin' => ['required', 'numeric', 'min:0', 'max:100'],
            'scaleMax' => ['required', 'numeric', 'min:0', 'max:100', 'gte:scaleMin'],
            'scaleIsPass' => ['boolean'],
            'scaleSort' => ['integer', 'min:0'],
        ]);

        $payload = [
            'grading_system_id' => $this->systemId,
            'label' => $this->scaleLabel,
            'min_percent' => $this->scaleMin,
            'max_percent' => $this->scaleMax,
            'is_pass' => $this->scaleIsPass,
            'sort_order' => $this->scaleSort,
        ];

        if ($this->editingScaleId) {
            SchoolGradeScale::query()->findOrFail($this->editingScaleId)->update($payload);
            notify()->success('Tranche mise à jour.');
        } else {
            SchoolGradeScale::query()->create($payload);
            notify()->success('Tranche ajoutée.');
        }
        $this->cancelScale();
    }

    public function deleteScale(int $id): void
    {
        SchoolGradeScale::query()->where('grading_system_id', $this->systemId)->where('id', $id)->delete();
        notify()->success('Tranche supprimée.');
    }

    protected function entity(): SchoolGradingSystem
    {
        return SchoolGradingSystem::query()->with('scales')->findOrFail($this->systemId);
    }

    public function render()
    {
        $system = $this->entity();
        $isManage = $this->mode === 'manage';

        return view('school::livewire.school.grading.systems-detail', [
            'system' => $system,
            'isManage' => $isManage,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$system->name,
            'subtitle' => $isManage ? 'Système, barème et actions.' : 'Détail du système de notation.',
        ]);
    }
}
