<?php

namespace InovCom\Suivi\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Suivi\Models\SiteReport;
use Livewire\Component;
use Livewire\WithPagination;

class SiteReportsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search     = '';
    public string $status     = '';
    public string $project_id = '';
    public int    $perPage    = 15;

    public function mount(): void
    {
        $this->tenantAuthorize('suivi.view');
    }

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedStatus(): void    { $this->resetPage(); }
    public function updatedProjectId(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('suivi.delete');
        $report = SiteReport::on('tenant')->findOrFail($id);
        $report->delete();
        notify()->success(__('Rapport supprimé.'));
    }

    public function render()
    {
        $reports = SiteReport::on('tenant')
            ->with(['project', 'client', 'assignedUser'])
            ->when($this->search !== '', fn($q) =>
                $q->where(fn($q) =>
                    $q->where('code', 'like', "%{$this->search}%")
                      ->orWhere('work_done', 'like', "%{$this->search}%")
                      ->orWhere('pv_client_name', 'like', "%{$this->search}%")
                )
            )
            ->when($this->status !== '', fn($q) => $q->where('status', $this->status))
            ->when($this->project_id !== '', fn($q) => $q->where('project_id', $this->project_id))
            ->ordered()
            ->paginate($this->perPage);

        // Project list for filter dropdown
        $projects = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('projects')
            ->whereNotIn('status', ['closed'])
            ->orderBy('title')
            ->get(['id', 'code', 'title']);

        return view('inovcom-suivi::livewire.reports.index', [
            'reports'  => $reports,
            'projects' => $projects,
            'canCreate' => $this->tenantCan('suivi.create'),
            'canDelete' => $this->tenantCan('suivi.delete'),
        ])->layout('layouts.app', [
            'title'    => __('Suivi terrain'),
            'subtitle' => __('Rapports journaliers de chantier, PV et avancement.'),
        ]);
    }
}
