<?php

namespace InovCom\Crm\Http\Livewire;

use InovCom\Crm\Concerns\AuthorizesCrmActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Prospects\Services\ProspectsService;
use InovCom\Users\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class CrmActivities extends Component
{
    use AuthorizesCrmActions;
    use WithPagination;

    public string $search = '';

    public string $type = '';

    public string $state = '';

    public string $ownerFilter = '';

    public string $assigneeFilter = '';

    public string $prospectFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $scope = 'all';

    public int $perPage = 20;

    public string $prospectSearch = '';

    public string $ownerSearch = '';

    public string $assigneeSearch = '';

    public string $prospectLabel = '';

    public string $ownerLabel = '';

    public string $assigneeLabel = '';

    /** @var list<array{id:int,name:string,meta:string}> */
    public array $prospectResults = [];

    /** @var list<array{id:int,name:string,meta:string}> */
    public array $ownerResults = [];

    /** @var list<array{id:int,name:string,meta:string}> */
    public array $assigneeResults = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingState(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedProspectSearch(): void
    {
        if ($this->prospectFilter !== '') {
            $this->prospectResults = [];

            return;
        }

        $this->prospectResults = $this->searchProspects(trim($this->prospectSearch));
    }

    public function updatedOwnerSearch(): void
    {
        if ($this->ownerFilter !== '') {
            $this->ownerResults = [];

            return;
        }

        $this->ownerResults = $this->searchUsers(trim($this->ownerSearch));
    }

    public function updatedAssigneeSearch(): void
    {
        if ($this->assigneeFilter !== '') {
            $this->assigneeResults = [];

            return;
        }

        $this->assigneeResults = $this->searchUsers(trim($this->assigneeSearch));
    }

    public function selectProspectFilter(int $id): void
    {
        $prospect = Prospect::query()->find($id, ['id', 'name', 'reference']);
        if (! $prospect) {
            return;
        }
        $this->prospectFilter = (string) $prospect->id;
        $this->prospectLabel = $prospect->name.($prospect->reference ? ' ('.$prospect->reference.')' : '');
        $this->prospectSearch = '';
        $this->prospectResults = [];
        $this->resetPage();
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
        $this->resetPage();
    }

    public function selectAssigneeFilter(int $id): void
    {
        $user = User::query()->find($id, ['id', 'name', 'email']);
        if (! $user) {
            return;
        }
        $this->assigneeFilter = (string) $user->id;
        $this->assigneeLabel = $user->name;
        $this->assigneeSearch = '';
        $this->assigneeResults = [];
        $this->resetPage();
    }

    public function setScope(string $scope): void
    {
        $this->scope = in_array($scope, ['all', 'planned', 'overdue', 'mine', 'done'], true) ? $scope : 'all';
        if (in_array($this->scope, ['planned', 'overdue', 'done'], true)) {
            $this->state = '';
        }
        $this->resetPage();
    }

    public function clearFilter(string $key): void
    {
        if ($key === 'dates') {
            $this->dateFrom = '';
            $this->dateTo = '';
        } elseif ($key === 'prospectFilter') {
            $this->clearProspectFilter();
        } elseif ($key === 'ownerFilter') {
            $this->clearOwnerFilter();
        } elseif ($key === 'assigneeFilter') {
            $this->clearAssigneeFilter();
        } else {
            match ($key) {
                'search' => $this->search = '',
                'type' => $this->type = '',
                'state' => $this->state = '',
                'dateFrom' => $this->dateFrom = '',
                'dateTo' => $this->dateTo = '',
                default => null,
            };
        }
        $this->resetPage();
    }

    public function clearProspectFilter(): void
    {
        $this->prospectFilter = '';
        $this->prospectLabel = '';
        $this->prospectSearch = '';
        $this->prospectResults = [];
        $this->resetPage();
    }

    public function clearOwnerFilter(): void
    {
        $this->ownerFilter = '';
        $this->ownerLabel = '';
        $this->ownerSearch = '';
        $this->ownerResults = [];
        $this->resetPage();
    }

    public function clearAssigneeFilter(): void
    {
        $this->assigneeFilter = '';
        $this->assigneeLabel = '';
        $this->assigneeSearch = '';
        $this->assigneeResults = [];
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->type = '';
        $this->state = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->scope = 'all';
        $this->prospectFilter = '';
        $this->prospectLabel = '';
        $this->prospectSearch = '';
        $this->prospectResults = [];
        $this->ownerFilter = '';
        $this->ownerLabel = '';
        $this->ownerSearch = '';
        $this->ownerResults = [];
        $this->assigneeFilter = '';
        $this->assigneeLabel = '';
        $this->assigneeSearch = '';
        $this->assigneeResults = [];
        $this->resetPage();
    }

    public function complete(int $activityId): void
    {
        $this->authorizeCrm('crm.activities.view');
        try {
            app(ProspectsService::class)->completeActivity(
                ProspectActivity::findOrFail($activityId)
            );
            session()->flash('success', 'Action terminée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancel(int $activityId): void
    {
        $this->authorizeCrm('crm.activities.view');
        try {
            app(ProspectsService::class)->cancelActivity(
                ProspectActivity::findOrFail($activityId)
            );
            session()->flash('success', 'Action annulée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * @return list<array{id:int,name:string,meta:string}>
     */
    protected function searchProspects(string $term): array
    {
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.mb_strtolower($term).'%';

        return Prospect::query()
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'reference', 'phone'])
            ->map(fn (Prospect $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'meta' => trim(implode(' · ', array_filter([
                    $p->reference,
                    $p->phone,
                ]))),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,meta:string}>
     */
    protected function searchUsers(string $term): array
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

    protected function resolveSelectedLabels(): void
    {
        if ($this->prospectFilter !== '' && $this->prospectLabel === '') {
            $p = Prospect::query()->find((int) $this->prospectFilter, ['id', 'name', 'reference']);
            $this->prospectLabel = $p
                ? $p->name.($p->reference ? ' ('.$p->reference.')' : '')
                : 'Prospect #'.$this->prospectFilter;
        }

        if ($this->ownerFilter !== '' && $this->ownerLabel === '') {
            $u = User::query()->find((int) $this->ownerFilter, ['id', 'name']);
            $this->ownerLabel = $u?->name ?? 'Commercial #'.$this->ownerFilter;
        }

        if ($this->assigneeFilter !== '' && $this->assigneeLabel === '') {
            $u = User::query()->find((int) $this->assigneeFilter, ['id', 'name']);
            $this->assigneeLabel = $u?->name ?? 'Assigné #'.$this->assigneeFilter;
        }
    }

    public function render()
    {
        $this->authorizeCrm('crm.activities.view');
        $this->resolveSelectedLabels();

        $userId = auth('tenant')->id();
        $typeOptions = ProspectActivity::typeOptions();
        $stateOptions = ProspectActivity::stateOptions();

        $query = ProspectActivity::query()
            ->with(['prospect.owner', 'user', 'assignee'])
            ->orderByRaw("CASE WHEN state = 'planned' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN state = \'planned\' THEN due_at ELSE NULL END ASC')
            ->orderByDesc('created_at');

        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(body) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(summary, \'\')) LIKE ?', [$term])
                    ->orWhereHas('prospect', fn ($pq) => $pq->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(reference) LIKE ?', [$term]));
            });
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->state !== '' && in_array($this->scope, ['all', 'mine'], true)) {
            $query->where('state', $this->state);
        }

        if ($this->ownerFilter !== '') {
            $query->whereHas('prospect', fn ($q) => $q->where('owner_id', (int) $this->ownerFilter));
        }

        if ($this->assigneeFilter !== '') {
            $query->where('assignee_id', (int) $this->assigneeFilter);
        }

        if ($this->prospectFilter !== '') {
            $query->where('prospect_id', (int) $this->prospectFilter);
        }

        if ($this->dateFrom !== '') {
            $query->where(function ($q) {
                $q->whereDate('due_at', '>=', $this->dateFrom)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('due_at')->whereDate('created_at', '>=', $this->dateFrom);
                    });
            });
        }

        if ($this->dateTo !== '') {
            $query->where(function ($q) {
                $q->whereDate('due_at', '<=', $this->dateTo)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('due_at')->whereDate('created_at', '<=', $this->dateTo);
                    });
            });
        }

        match ($this->scope) {
            'planned' => $query->where('state', ProspectActivity::STATE_PLANNED),
            'overdue' => $query->where('state', ProspectActivity::STATE_PLANNED)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now()),
            'mine' => $query->where(function ($q) use ($userId) {
                $q->where('assignee_id', $userId)
                    ->orWhere('user_id', $userId)
                    ->orWhereHas('prospect', fn ($pq) => $pq->where('owner_id', $userId));
            }),
            'done' => $query->where('state', ProspectActivity::STATE_DONE),
            default => null,
        };

        $counts = [
            'all' => ProspectActivity::query()->count(),
            'planned' => ProspectActivity::query()->where('state', ProspectActivity::STATE_PLANNED)->count(),
            'overdue' => ProspectActivity::query()
                ->where('state', ProspectActivity::STATE_PLANNED)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'mine' => ProspectActivity::query()
                ->where(function ($q) use ($userId) {
                    $q->where('assignee_id', $userId)
                        ->orWhere('user_id', $userId);
                })
                ->count(),
            'done' => ProspectActivity::query()->where('state', ProspectActivity::STATE_DONE)->count(),
        ];

        $activeChips = [];
        if (trim($this->search) !== '') {
            $activeChips[] = ['key' => 'search', 'label' => '« '.trim($this->search).' »'];
        }
        if ($this->type !== '') {
            $activeChips[] = ['key' => 'type', 'label' => $typeOptions[$this->type] ?? $this->type];
        }
        if ($this->state !== '' && in_array($this->scope, ['all', 'mine'], true)) {
            $activeChips[] = ['key' => 'state', 'label' => $stateOptions[$this->state] ?? $this->state];
        }
        if ($this->prospectFilter !== '') {
            $activeChips[] = ['key' => 'prospectFilter', 'label' => $this->prospectLabel !== '' ? $this->prospectLabel : 'Prospect'];
        }
        if ($this->ownerFilter !== '') {
            $activeChips[] = ['key' => 'ownerFilter', 'label' => 'Carte : '.($this->ownerLabel !== '' ? $this->ownerLabel : '—')];
        }
        if ($this->assigneeFilter !== '') {
            $activeChips[] = ['key' => 'assigneeFilter', 'label' => 'Assigné : '.($this->assigneeLabel !== '' ? $this->assigneeLabel : '—')];
        }
        if ($this->dateFrom !== '' || $this->dateTo !== '') {
            $from = $this->dateFrom !== '' ? \Illuminate\Support\Carbon::parse($this->dateFrom)->format('d/m/Y') : '…';
            $to = $this->dateTo !== '' ? \Illuminate\Support\Carbon::parse($this->dateTo)->format('d/m/Y') : '…';
            $activeChips[] = ['key' => 'dates', 'label' => $from.' → '.$to];
        }

        $perPage = in_array($this->perPage, [10, 20, 50], true) ? $this->perPage : 20;

        return view('inovcom-crm::livewire.activities', [
            'activities' => $query->paginate($perPage),
            'typeOptions' => $typeOptions,
            'stateOptions' => $stateOptions,
            'counts' => $counts,
            'activeChips' => $activeChips,
            'hasActiveFilters' => $activeChips !== [] || $this->scope !== 'all',
            'showStateFilter' => in_array($this->scope, ['all', 'mine'], true),
        ])->layout('layouts.app', [
            'title' => 'Activités CRM',
            'subtitle' => 'Historique & actions planifiées',
        ]);
    }
}
