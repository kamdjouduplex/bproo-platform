<?php

namespace InovCom\Projets\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Projets\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('projets.view');

        if ($this->typeFilter === '') {
            $this->typeFilter = ServiceCatalog::EXEC_CONSTRUCTION;
        }
    }

    public string $search = '';
    public int $perPage = 10;
    public string $typeFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $project = Project::findOrFail($id);
        $project->delete();
        session()->flash('message', __('Projet supprimé.'));
    }

    public function render()
    {
        $projects = Project::query()
            ->with(['client', 'quote', 'assignedUser'])
            ->when($this->typeFilter === ServiceCatalog::EXEC_SERVICE, function ($q) {
                $q->where('project_type', ServiceCatalog::EXEC_SERVICE);
            })
            ->when($this->typeFilter === ServiceCatalog::EXEC_CONSTRUCTION, function ($q) {
                $q->where(function ($q) {
                    $q->where('project_type', ServiceCatalog::EXEC_CONSTRUCTION)
                        ->orWhereNull('project_type')
                        ->orWhere('project_type', ServiceCatalog::EXEC_OTHER);
                });
            })
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->ordered()
            ->paginate($this->perPage);

        $isPrestations = $this->typeFilter === ServiceCatalog::EXEC_SERVICE;
        $tenantCode = session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;

        return view('inovcom-projets::livewire.projects.index', [
            'projects'       => $projects,
            'isPrestations'  => $isPrestations,
            'typeFilter'     => $this->typeFilter,
            'tenantCode'     => $tenantCode,
            'catalog'        => ServiceCatalog::class,
        ])
            ->layout('layouts.app', [
                'title' => $isPrestations ? __('Prestations ponctuelles') : __('Chantiers & projets'),
                'subtitle' => $isPrestations
                    ? __('Interventions courtes et missions ponctuelles.')
                    : __('Exécution des chantiers à partir des devis acceptés.'),
            ]);
    }
}
