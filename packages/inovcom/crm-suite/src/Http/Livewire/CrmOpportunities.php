<?php

namespace InovCom\Crm\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Crm\Concerns\AuthorizesCrmActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Prospects\Services\ProspectsService;
use InovCom\Users\Models\User;
use Livewire\Component;

class CrmOpportunities extends Component
{
    use AuthorizesCrmActions;

    public string $ownerFilter = '';

    public string $ownerSearch = '';

    public string $ownerLabel = '';

    /** @var list<array{id:int,name:string,meta:string}> */
    public array $ownerResults = [];

    public string $boardSearch = '';

    public ?int $selectedProspectId = null;

    public bool $showAssignModal = false;

    public bool $showScheduleModal = false;

    public bool $showPanelActions = false;

    public ?int $modalProspectId = null;

    public string $modalProspectName = '';

    public string $assignOwnerId = '';

    public string $scheduleType = 'call';

    public string $scheduleSummary = '';

    public string $scheduleBody = '';

    public string $scheduleDueAt = '';

    public string $scheduleAssigneeId = '';

    public function selectProspect(int $prospectId): void
    {
        $this->selectedProspectId = $prospectId;
        $this->showPanelActions = false;
    }

    public function updatedOwnerSearch(): void
    {
        if ($this->ownerFilter !== '') {
            $this->ownerResults = [];

            return;
        }

        $this->ownerResults = $this->searchOwners(trim($this->ownerSearch));
    }

    public function selectOwnerFilter(int $id): void
    {
        $user = User::query()->find($id, ['id', 'name', 'email']);
        if (! $user) {
            return;
        }
        $this->ownerFilter = (string) $user->id;
        $this->ownerLabel = $user->name;
        $this->ownerSearch = '';
        $this->ownerResults = [];
    }

    public function clearOwnerFilter(): void
    {
        $this->ownerFilter = '';
        $this->ownerLabel = '';
        $this->ownerSearch = '';
        $this->ownerResults = [];
    }

    /**
     * @return list<array{id:int,name:string,meta:string}>
     */
    protected function searchOwners(string $term): array
    {
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.mb_strtolower($term).'%';

        return User::query()
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'meta' => (string) ($u->email ?? ''),
            ])
            ->all();
    }

    protected function resolveOwnerLabel(): void
    {
        if ($this->ownerFilter !== '' && $this->ownerLabel === '') {
            $u = User::query()->find((int) $this->ownerFilter, ['id', 'name']);
            $this->ownerLabel = $u?->name ?? 'Commercial #'.$this->ownerFilter;
        }
    }

    public function closePanel(): void
    {
        $this->selectedProspectId = null;
        $this->showPanelActions = false;
    }

    public function togglePanelActions(): void
    {
        $this->showPanelActions = ! $this->showPanelActions;
    }

    public function initiate(int $prospectId): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        try {
            app(ProspectsService::class)->initiateAsProspect(
                Prospect::findOrFail($prospectId),
                Auth::guard('tenant')->id()
            );
            $this->selectedProspectId = $prospectId;
            session()->flash('success', 'Prospect placé en Qualifié.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function advance(int $prospectId): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $prospect = Prospect::findOrFail($prospectId);
        if ($prospect->isConverted() || $prospect->isLost()) {
            return;
        }

        $order = Prospect::pipelineStatuses();
        $idx = array_search($prospect->status, $order, true);
        if ($idx === false || $idx >= count($order) - 1) {
            return;
        }

        app(ProspectsService::class)->changeStatus(
            $prospect,
            $order[$idx + 1],
            null,
            null,
            Auth::guard('tenant')->id()
        );
        $this->selectedProspectId = $prospectId;
    }

    public function markLost(int $prospectId): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $prospect = Prospect::findOrFail($prospectId);
        if ($prospect->isConverted() || $prospect->isLost()) {
            return;
        }
        app(ProspectsService::class)->changeStatus(
            $prospect,
            Prospect::STATUS_PERDU,
            'Perdu depuis le pipeline CRM',
            null,
            Auth::guard('tenant')->id()
        );
        if ($this->selectedProspectId === $prospectId) {
            $this->closePanel();
        }
    }

    public function convert(int $prospectId): void
    {
        $this->authorizeCrm('crm.prospects.convert');
        $prospect = Prospect::findOrFail($prospectId);

        if ($prospect->status !== Prospect::STATUS_GAGNE) {
            session()->flash('error', 'Passez le prospect en Gagné avant de convertir.');

            return;
        }

        if (! $prospect->isReadyToConvert()) {
            session()->flash('error', 'Complétez la fiche : '.implode(' ', $prospect->conversionGaps()));
            $this->selectedProspectId = $prospectId;

            return;
        }

        try {
            $result = app(ProspectsService::class)->convertToClient(
                $prospect,
                Auth::guard('tenant')->id()
            );
            $client = $result['client'];
            session()->flash('success', 'Converti en client '.$client->code.'.');

            if (Route::has('tenant.clients.show')) {
                $this->redirect(route('tenant.clients.show', $client), navigate: true);
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            $this->selectedProspectId = $prospectId;
        }
    }

    public function openAssignModal(int $prospectId): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $prospect = Prospect::findOrFail($prospectId);
        $this->modalProspectId = $prospect->id;
        $this->modalProspectName = $prospect->name;
        $this->assignOwnerId = $prospect->owner_id ? (string) $prospect->owner_id : '';
        $this->showAssignModal = true;
        $this->showScheduleModal = false;
        $this->showPanelActions = false;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->modalProspectId = null;
    }

    public function saveAssign(): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        if (! $this->modalProspectId) {
            return;
        }

        try {
            app(ProspectsService::class)->assignOwner(
                Prospect::findOrFail($this->modalProspectId),
                $this->assignOwnerId !== '' ? (int) $this->assignOwnerId : null,
                Auth::guard('tenant')->id()
            );
            $this->selectedProspectId = $this->modalProspectId;
            $this->closeAssignModal();
            session()->flash('success', 'Commercial assigné.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openScheduleModal(int $prospectId): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $prospect = Prospect::findOrFail($prospectId);
        $this->modalProspectId = $prospect->id;
        $this->modalProspectName = $prospect->name;
        $this->scheduleType = ProspectActivity::TYPE_CALL;
        $this->scheduleSummary = '';
        $this->scheduleBody = '';
        $this->scheduleDueAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->scheduleAssigneeId = $prospect->owner_id
            ? (string) $prospect->owner_id
            : (string) (Auth::guard('tenant')->id() ?? '');
        $this->showScheduleModal = true;
        $this->showAssignModal = false;
        $this->showPanelActions = false;
    }

    public function closeScheduleModal(): void
    {
        $this->showScheduleModal = false;
        $this->modalProspectId = null;
    }

    public function saveSchedule(): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        if (! $this->modalProspectId) {
            return;
        }

        $this->validate([
            'scheduleType' => 'required|in:'.ProspectActivity::actionableTypeKeys(),
            'scheduleDueAt' => 'required|date',
            'scheduleSummary' => 'nullable|string|max:180',
            'scheduleBody' => 'nullable|string|max:5000',
            'scheduleAssigneeId' => 'nullable',
        ]);

        try {
            app(ProspectsService::class)->scheduleActivity(
                Prospect::findOrFail($this->modalProspectId),
                [
                    'type' => $this->scheduleType,
                    'summary' => $this->scheduleSummary,
                    'body' => $this->scheduleBody,
                    'due_at' => $this->scheduleDueAt,
                    'assignee_id' => $this->scheduleAssigneeId !== '' ? (int) $this->scheduleAssigneeId : null,
                ],
                Auth::guard('tenant')->id()
            );
            $this->selectedProspectId = $this->modalProspectId;
            $this->closeScheduleModal();
            session()->flash('success', 'Prochaine action planifiée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeNextAction(int $activityId): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        try {
            $activity = ProspectActivity::findOrFail($activityId);
            app(ProspectsService::class)->completeActivity($activity, null, Auth::guard('tenant')->id());
            if ($activity->prospect_id) {
                $this->selectedProspectId = (int) $activity->prospect_id;
            }
            session()->flash('success', 'Action marquée comme terminée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function logQuickActivity(int $prospectId, string $type): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $labels = [
            ProspectActivity::TYPE_CALL => 'Appel effectué.',
            ProspectActivity::TYPE_EMAIL => 'E-mail envoyé.',
            ProspectActivity::TYPE_NOTE => 'Note ajoutée.',
        ];
        if (! isset($labels[$type])) {
            return;
        }

        try {
            $prospect = Prospect::findOrFail($prospectId);
            app(ProspectsService::class)->addActivity(
                $prospect,
                $type,
                $labels[$type],
                Auth::guard('tenant')->id()
            );
            $this->selectedProspectId = $prospectId;
            session()->flash('success', $labels[$type]);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $this->authorizeCrm('crm.opportunities.view');
        $this->resolveOwnerLabel();

        $columns = [
            Prospect::STATUS_QUALIFIE => [
                'label' => 'Qualifiés',
                'hint' => 'Besoin identifié',
                'tone' => 'blue',
            ],
            Prospect::STATUS_NEGOCIATION => [
                'label' => 'Négociation',
                'hint' => 'Offre en cours',
                'tone' => 'violet',
            ],
            Prospect::STATUS_GAGNE => [
                'label' => 'Gagnés',
                'hint' => 'Prêts à convertir',
                'tone' => 'green',
            ],
        ];

        $query = Prospect::query()
            ->with(['owner', 'nextPlannedActivity.assignee'])
            ->withCount([
                'plannedActivities as planned_count',
            ])
            ->whereIn('status', array_keys($columns))
            ->orderByDesc('updated_at');

        if ($this->ownerFilter !== '') {
            $query->where('owner_id', (int) $this->ownerFilter);
        }

        $term = trim($this->boardSearch);
        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
            });
        }

        $all = $query->get();
        $items = $all->groupBy('status');
        $pipelineValue = (float) $all->sum('expected_value');
        $columnTotals = [];
        foreach (array_keys($columns) as $status) {
            $columnTotals[$status] = (float) ($items[$status] ?? collect())->sum('expected_value');
        }

        $selected = null;
        if ($this->selectedProspectId) {
            $selected = Prospect::query()
                ->with([
                    'owner',
                    'creator',
                    'nextPlannedActivity.assignee',
                    'activities' => fn ($q) => $q->with(['user', 'assignee'])->orderByDesc('created_at')->limit(12),
                ])
                ->find($this->selectedProspectId);
            if (! $selected) {
                $this->selectedProspectId = null;
            }
        }

        return view('inovcom-crm::livewire.opportunities', [
            'columns' => $columns,
            'items' => $items,
            'columnTotals' => $columnTotals,
            'pipelineValue' => $pipelineValue,
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $this->canCrm('crm.opportunities.manage'),
            'canConvert' => $this->canCrm('crm.prospects.convert'),
            'actionTypes' => ProspectActivity::actionableTypeOptions(),
            'selected' => $selected,
        ])->layout('layouts.app', [
            'title' => 'Opportunités',
            'subtitle' => 'Pipeline commercial',
        ]);
    }
}
