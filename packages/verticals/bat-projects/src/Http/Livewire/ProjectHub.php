<?php

namespace InovCom\Projets\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Kernel\Exceptions\InvalidWorkflowTransitionException;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Projets\Models\Project;
use InovCom\Projets\Services\ProjectHubService;
use Livewire\Component;

class ProjectHub extends Component
{
    use AuthorizesWithTenant;

    public int $projectId;
    public string $status = 'planned';

    public function mount(Project $project): void
    {
        $this->tenantAuthorize('projets.view');
        $this->projectId = (int) $project->id;
        $this->status = $project->status;
    }

    public function startProject(): void
    {
        $this->applyWorkflow('in_progress', __('Chantier démarré.'));
    }

    public function holdProject(): void
    {
        $this->applyWorkflow('on_hold', __('Chantier mis en attente.'));
    }

    public function completeProject(): void
    {
        $this->applyWorkflow('completed', __('Chantier marqué comme terminé.'));
    }

    public function closeProject(): void
    {
        $this->applyWorkflow('closed', __('Chantier clôturé.'));
    }

    private function applyWorkflow(string $to, string $success): void
    {
        $this->tenantAuthorize('projets.edit');
        $project = Project::on('tenant')->findOrFail($this->projectId);
        try {
            $project->transitionTo($to, auth('tenant')->id());
            $this->status = $to;
            notify()->success($success);
        } catch (InvalidWorkflowTransitionException $e) {
            notify()->error($e->getMessage());
        }
    }

    public function render(ProjectHubService $hub)
    {
        $project = Project::on('tenant')
            ->with(['client', 'quote', 'assignedUser'])
            ->findOrFail($this->projectId);

        $this->status = $project->status;

        $tenantCode = session('tenant_code')
            ?? optional(app(TenantManager::class)->tenant())->code;

        $snapshot = $hub->snapshot($project, (string) $tenantCode);
        $isPrestation = ($project->project_type ?? '') === ServiceCatalog::EXEC_SERVICE;

        return view('inovcom-projets::livewire.projects.hub', [
            'project'            => $project,
            'snapshot'           => $snapshot,
            'tenantCode'         => $tenantCode,
            'isPrestation'       => $isPrestation,
            'allowedTransitions' => $project->allowedTransitions()[$this->status] ?? [],
            'statusLabel'        => $this->statusLabel($this->status),
            'statusClass'        => $this->statusClass($this->status),
            'canEdit'            => $this->tenantCan('projets.edit'),
        ])->layout('layouts.app', [
            'title'    => $project->title,
            'subtitle' => $project->code,
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'planned'     => __('Planifié'),
            'in_progress' => __('En cours'),
            'on_hold'     => __('En attente'),
            'completed'   => __('Terminé'),
            'closed'      => __('Clôturé'),
            default       => $status,
        };
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'planned'     => 'bg-slate-100 text-slate-700',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'on_hold'     => 'bg-amber-100 text-amber-800',
            'completed'   => 'bg-emerald-100 text-emerald-800',
            'closed'      => 'bg-slate-200 text-slate-500',
            default       => 'bg-slate-100 text-slate-600',
        };
    }
}
