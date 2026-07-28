<?php

namespace Pressing\Http\Livewire\Workflow;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\WorkflowStage;

class WorkflowStagesIndex extends Component
{
    use AuthorizesPressingActions;

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $color = '#64748b';
    public string $sort_order = '1';
    public ?string $estimated_minutes = null;
    public bool $is_final = false;
    public bool $is_active = true;

    public function create(): void
    {
        $this->authorizePressingAction('pressing_workflow.manage');
        $this->resetForm();
        $this->sort_order = (string) ((int) WorkflowStage::max('sort_order') + 1);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePressingAction('pressing_workflow.manage');
        $stage = WorkflowStage::findOrFail($id);
        $this->editingId = $stage->id;
        $this->name = $stage->name;
        $this->color = $stage->color;
        $this->sort_order = (string) $stage->sort_order;
        $this->estimated_minutes = $stage->estimated_minutes ? (string) $stage->estimated_minutes : null;
        $this->is_final = $stage->is_final;
        $this->is_active = $stage->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_workflow.manage');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'is_final' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $payload = [
            'name' => $data['name'],
            'color' => $data['color'],
            'sort_order' => (int) $data['sort_order'],
            'estimated_minutes' => $data['estimated_minutes'] ? (int) $data['estimated_minutes'] : null,
            'is_final' => $data['is_final'],
            'is_active' => $data['is_active'],
            'agence_id' => null,
        ];

        if ($this->editingId) {
            WorkflowStage::findOrFail($this->editingId)->update($payload);
            session()->flash('success', 'Étape mise à jour.');
        } else {
            WorkflowStage::create($payload);
            session()->flash('success', 'Étape créée.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizePressingAction('pressing_workflow.manage');
        $stage = WorkflowStage::findOrFail($id);

        $activeCount = $stage->orders()->whereIn('status', ['open', 'ready'])->count();
        if ($activeCount > 0) {
            session()->flash(
                'error',
                "Étape utilisée par {$activeCount} commande(s) en cours. Déplacez-les ou désactivez l’étape."
            );

            return;
        }

        $stage->delete();
        session()->flash('success', 'Étape supprimée.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->color = '#64748b';
        $this->sort_order = '1';
        $this->estimated_minutes = null;
        $this->is_final = false;
        $this->is_active = true;
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_workflow.manage');

        return view('pressing::livewire.workflow.stages', [
            'stages' => WorkflowStage::whereNull('agence_id')->orderBy('sort_order')->get(),
        ])->layout('layouts.app', ['title' => 'Étapes workflow']);
    }
}
