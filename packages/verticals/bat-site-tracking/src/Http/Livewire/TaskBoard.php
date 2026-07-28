<?php

namespace InovCom\Suivi\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Carbon\Carbon;
use InovCom\Projets\Models\Project;
use InovCom\Suivi\Models\ProjectTask;
use InovCom\Suivi\Models\SiteReport;
use InovCom\Users\Models\User;
use Livewire\Component;

class TaskBoard extends Component
{
    use AuthorizesWithTenant;

    public string $projectId  = '';
    public string $activeTab  = 'kanban';
    public string $periodType = 'week';
    public string $periodDate = '';
    public bool   $filterLate = false;

    // Task modal — shared for add and edit
    public bool   $showTaskModal  = false;
    public ?int   $editTaskId     = null;
    public string $newTitle       = '';
    public string $newDescription = '';
    public array  $newAssigneeIds = [];
    public string $newStatus      = 'todo';
    public string $newDueDate     = '';

    public function mount(): void
    {
        $this->tenantAuthorize('suivi.view');
        $this->periodDate = $this->defaultPeriodDate();
    }

    // ── Period helpers ────────────────────────────────────────────────────────

    protected function defaultPeriodDate(): string
    {
        return $this->periodType === 'week'
            ? now()->startOfWeek(Carbon::MONDAY)->toDateString()
            : now()->toDateString();
    }

    public function updatedPeriodType(): void
    {
        $this->periodDate = $this->defaultPeriodDate();
    }

    public function previousPeriod(): void
    {
        $date = Carbon::parse($this->periodDate);
        $this->periodDate = $this->periodType === 'day'
            ? $date->subDay()->toDateString()
            : $date->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function nextPeriod(): void
    {
        $date = Carbon::parse($this->periodDate);
        $this->periodDate = $this->periodType === 'day'
            ? $date->addDay()->toDateString()
            : $date->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    protected function getPeriodRange(): array
    {
        if ($this->periodType === 'day') {
            return [$this->periodDate, $this->periodDate];
        }
        $start = Carbon::parse($this->periodDate)->startOfWeek(Carbon::MONDAY);
        $end   = $start->copy()->addDays(5); // Monday → Saturday (6 working days)
        return [$start->toDateString(), $end->toDateString()];
    }

    protected function getPeriodLabel(): string
    {
        if ($this->periodType === 'day') {
            return ucfirst(Carbon::parse($this->periodDate)->locale('fr')->isoFormat('dddd D MMMM YYYY'));
        }
        $start = Carbon::parse($this->periodDate)->startOfWeek(Carbon::MONDAY)->locale('fr');
        $end   = $start->copy()->addDays(5);
        return 'Semaine du ' . $start->isoFormat('D MMM') . ' au ' . $end->isoFormat('D MMM YYYY');
    }

    protected function effectivePeriodDate(): string
    {
        if ($this->periodType === 'week') {
            return Carbon::parse($this->periodDate)->startOfWeek(Carbon::MONDAY)->toDateString();
        }
        return $this->periodDate;
    }

    // ── Task modal ────────────────────────────────────────────────────────────

    public function openAddModal(string $status = 'todo'): void
    {
        $this->tenantAuthorize('suivi.create');
        $this->resetValidation();
        $this->editTaskId     = null;
        $this->newStatus      = $status;
        $this->newTitle       = '';
        $this->newDescription = '';
        $this->newAssigneeIds = [];
        $this->newDueDate     = '';
        $this->showTaskModal  = true;
    }

    public function openEditModal(int $taskId): void
    {
        $this->tenantAuthorize('suivi.edit');
        $this->resetValidation();
        $task = ProjectTask::on('tenant')->findOrFail($taskId);

        $this->editTaskId     = $taskId;
        $this->newTitle       = $task->title;
        $this->newDescription = $task->description ?? '';
        $this->newStatus      = $task->status;
        $this->newAssigneeIds = array_map('strval', $task->assignee_ids ?? []);
        $this->newDueDate     = $task->due_date ? $task->due_date->format('Y-m-d') : '';
        $this->showTaskModal  = true;
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->editTaskId    = null;
    }

    public function saveTask(): void
    {
        if ($this->editTaskId) {
            $this->tenantAuthorize('suivi.edit');
        } else {
            $this->tenantAuthorize('suivi.create');
        }

        $this->validate([
            'newTitle'       => ['required', 'string', 'max:255'],
            'newDescription' => ['nullable', 'string'],
            'newDueDate'     => ['nullable', 'date'],
            'newStatus'      => ['required', 'in:todo,in_progress,done'],
        ]);

        $data = [
            'title'        => $this->newTitle,
            'description'  => $this->newDescription ?: null,
            'status'       => $this->newStatus,
            'assignee_ids' => empty($this->newAssigneeIds)
                ? null
                : array_map('intval', $this->newAssigneeIds),
            'due_date'     => $this->newDueDate ?: null,
        ];

        if ($this->editTaskId) {
            $task = ProjectTask::on('tenant')->findOrFail($this->editTaskId);

            if ($data['status'] !== 'done' && $task->is_validated) {
                $data['is_validated'] = false;
                $data['validated_at'] = null;
                $data['validated_by'] = null;
            }

            $task->update($data);
            $this->showTaskModal = false;
            $this->editTaskId    = null;
            notify()->success(__('Tâche mise à jour.'));
        } else {
            if (!$this->projectId) {
                $this->addError('newTitle', __('Veuillez d\'abord sélectionner un projet.'));
                return;
            }

            ProjectTask::on('tenant')->create(array_merge($data, [
                'code'         => ProjectTask::generateCode(),
                'project_id'   => (int) $this->projectId,
                'period_type'  => $this->periodType,
                'period_date'  => $this->effectivePeriodDate(),
                'is_validated' => false,
            ]));

            $this->showTaskModal = false;
            notify()->success(__('Tâche ajoutée.'));
        }
    }

    // ── Board actions ─────────────────────────────────────────────────────────

    public function moveTask(int $taskId, string $newStatus): void
    {
        $this->tenantAuthorize('suivi.edit');
        if (!in_array($newStatus, ['todo', 'in_progress', 'done'], true)) {
            return;
        }

        $task         = ProjectTask::on('tenant')->findOrFail($taskId);
        $task->status = $newStatus;

        if ($newStatus !== 'done' && $task->is_validated) {
            $task->is_validated = false;
            $task->validated_at = null;
            $task->validated_by = null;
        }

        $task->save();
    }

    public function validateTask(int $taskId): void
    {
        $this->tenantAuthorize('suivi.validate');
        $task               = ProjectTask::on('tenant')->findOrFail($taskId);
        $task->status       = 'done';
        $task->is_validated = true;
        $task->validated_at = now();
        $task->validated_by = auth('tenant')->id();
        $task->save();

        notify()->success(__('Tâche validée.'));
    }

    public function invalidateTask(int $taskId): void
    {
        $this->tenantAuthorize('suivi.validate');
        $task               = ProjectTask::on('tenant')->findOrFail($taskId);
        $task->is_validated = false;
        $task->validated_at = null;
        $task->validated_by = null;
        $task->save();

        notify()->info(__('Validation retirée.'));
    }

    public function deleteTask(int $taskId): void
    {
        $this->tenantAuthorize('suivi.delete');
        ProjectTask::on('tenant')->findOrFail($taskId)->delete();
        notify()->success(__('Tâche supprimée.'));
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $projects = Project::on('tenant')
            ->whereIn('status', ['planned', 'in_progress', 'on_hold'])
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'status', 'progress_percent']);

        $users     = User::on('tenant')->orderBy('name')->get(['id', 'name']);
        $usersById = $users->keyBy('id');

        $columns        = ['todo' => collect(), 'in_progress' => collect(), 'done' => collect()];
        $stats          = ['total' => 0, 'todo' => 0, 'in_progress' => 0, 'done' => 0, 'validated' => 0];
        $currentProject = null;
        $reports        = null;

        if ($this->projectId) {
            $currentProject = Project::on('tenant')->find((int) $this->projectId);

            $allTasks = ProjectTask::on('tenant')
                ->where('project_id', (int) $this->projectId)
                ->get(['status', 'is_validated']);

            $stats['total']       = $allTasks->count();
            $stats['todo']        = $allTasks->where('status', 'todo')->count();
            $stats['in_progress'] = $allTasks->where('status', 'in_progress')->count();
            $stats['done']        = $allTasks->where('status', 'done')->count();
            $stats['validated']   = $allTasks->where('is_validated', true)->count();

            if ($this->activeTab === 'kanban') {
                $query = ProjectTask::on('tenant')
                    ->where('project_id', (int) $this->projectId);

                if ($this->filterLate) {
                    $query->whereNotNull('due_date')
                          ->where('due_date', '<', now()->toDateString())
                          ->where('is_validated', false);
                } else {
                    [$periodStart, $periodEnd] = $this->getPeriodRange();
                    $query->whereBetween('period_date', [$periodStart, $periodEnd]);
                }

                $tasks = $query->orderByRaw('is_validated asc')
                               ->orderBy('due_date')
                               ->orderBy('position')
                               ->orderBy('created_at')
                               ->get();

                foreach ($tasks as $task) {
                    $columns[$task->status][] = $task;
                }
            }

            if ($this->activeTab === 'rapports') {
                $reports = SiteReport::on('tenant')
                    ->where('project_id', (int) $this->projectId)
                    ->orderBy('report_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();
            }
        }

        return view('inovcom-suivi::livewire.task-board.index', [
            'projects'       => $projects,
            'users'          => $users,
            'usersById'      => $usersById,
            'columns'        => $columns,
            'stats'          => $stats,
            'currentProject' => $currentProject,
            'periodLabel'    => $this->getPeriodLabel(),
            'reports'        => $reports,
            'canCreate'      => $this->tenantCan('suivi.create'),
            'canEdit'        => $this->tenantCan('suivi.edit'),
            'canDelete'      => $this->tenantCan('suivi.delete'),
            'canValidate'    => $this->tenantCan('suivi.validate'),
        ])->layout('layouts.app', [
            'title'    => __('Suivi terrain'),
            'subtitle' => __('Tableau Kanban'),
        ]);
    }
}
