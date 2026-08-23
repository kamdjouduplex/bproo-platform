<?php

namespace InovCom\Crm\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Crm\Concerns\AuthorizesCrmActions;
use InovCom\Crm\Models\Opportunity;
use InovCom\Crm\Services\OpportunityService;
use InovCom\Crm\Support\CrmVisibility;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Users\Models\User;
use Livewire\Component;

class CrmOpportunities extends Component
{
    use AuthorizesCrmActions;

    public string $ownerFilter = '';

    public string $boardSearch = '';

    public string $viewMode = 'kanban';

    public bool $showCreateModal = false;

    public bool $showLostModal = false;

    public bool $showScheduleModal = false;

    public ?int $modalOpportunityId = null;

    public string $createProspectSearch = '';

    public string $createProspectId = '';

    public string $createProspectLabel = '';

    /** @var list<array{id:int,name:string,meta:string}> */
    public array $createProspectResults = [];

    public string $createTitle = '';

    public string $createAmount = '';

    public string $createCloseDate = '';

    public string $createOwnerId = '';

    public string $createNextSummary = '';

    public string $createNextDue = '';

    public string $createNextType = 'call';

    public string $lostReason = '';

    public string $lostComment = '';

    public string $scheduleType = 'call';

    public string $scheduleSummary = '';

    public string $scheduleDueAt = '';

    public ?string $pendingStage = null;

    public ?string $boardMessage = null;

    public function updatedCreateProspectSearch(): void
    {
        $term = trim($this->createProspectSearch);
        if ($this->createProspectId !== '' || mb_strlen($term) < 2) {
            $this->createProspectResults = [];

            return;
        }

        $like = '%'.mb_strtolower($term).'%';
        $this->createProspectResults = Prospect::query()
            ->whereNotIn('status', [Prospect::STATUS_NON_QUALIFIE])
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'company_name', 'reference'])
            ->map(fn (Prospect $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'meta' => trim(implode(' · ', array_filter([$p->company_name, $p->reference]))),
            ])
            ->all();
    }

    public function selectCreateProspect(int $id): void
    {
        $p = Prospect::find($id);
        if (! $p) {
            return;
        }
        $this->createProspectId = (string) $p->id;
        $this->createProspectLabel = $p->companyDisplayName() !== '—' ? $p->companyDisplayName() : $p->name;
        $this->createProspectSearch = '';
        $this->createProspectResults = [];
        $this->createTitle = $p->need ?: $p->product_interest ?: ('Opportunité '.$p->name);
        $this->createAmount = $p->expected_value !== null ? (string) $p->expected_value : '';
        $this->createOwnerId = $p->owner_id ? (string) $p->owner_id : (string) (Auth::guard('tenant')->id() ?? '');
    }

    public function openCreateModal(?int $prospectId = null): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $this->resetCreateForm();
        $this->createNextDue = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->createOwnerId = (string) (Auth::guard('tenant')->id() ?? '');
        if ($prospectId) {
            $this->selectCreateProspect($prospectId);
        }
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    public function saveCreate(): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $this->validate([
            'createProspectId' => 'required',
            'createTitle' => 'required|string|max:180',
            'createNextDue' => 'required|date',
            'createNextSummary' => 'required|string|max:180',
            'createOwnerId' => 'required',
        ], [
            'createProspectId.required' => 'Choisissez un prospect.',
            'createNextDue.required' => 'La prochaine action est obligatoire.',
            'createNextSummary.required' => 'Indiquez la prochaine action.',
            'createOwnerId.required' => 'Le commercial responsable est obligatoire.',
        ]);

        try {
            app(OpportunityService::class)->createFromProspect(
                Prospect::findOrFail((int) $this->createProspectId),
                [
                    'title' => $this->createTitle,
                    'amount' => $this->createAmount !== '' ? $this->createAmount : null,
                    'owner_id' => (int) $this->createOwnerId,
                    'expected_close_date' => $this->createCloseDate ?: null,
                    'next_action' => [
                        'type' => $this->createNextType,
                        'summary' => $this->createNextSummary,
                        'due_at' => $this->createNextDue,
                        'assignee_id' => (int) $this->createOwnerId,
                    ],
                ],
                Auth::guard('tenant')->id()
            );
            $this->closeCreateModal();
            session()->now('success', 'Opportunité créée.');
        } catch (\Throwable $e) {
            session()->now('error', $e->getMessage());
        }
    }

    public function moveToStage(int $id, string $stage): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $this->boardMessage = null;

        if ($stage === Opportunity::STAGE_PERDU) {
            $this->pendingStage = null;
            $this->openLostModal($id);

            return;
        }

        try {
            $opp = Opportunity::query()->with('nextPlannedActivity')->findOrFail($id);
            if ($opp->stage === $stage) {
                return;
            }

            $gaps = $opp->stageGaps($stage);
            if ($gaps === ['Prochaine action']) {
                $this->pendingStage = $stage;
                $this->openScheduleModal($id, true);
                $this->boardMessage = 'Pour changer d’étape, planifiez d’abord la prochaine action.';

                return;
            }
            if ($gaps !== []) {
                $this->boardMessage = 'Impossible d’avancer : '.implode(', ', $gaps).'.';

                return;
            }

            if ($stage === Opportunity::STAGE_GAGNE) {
                app(OpportunityService::class)->markWon($opp, Auth::guard('tenant')->id());
                session()->now('success', 'Opportunité gagnée.');

                return;
            }

            app(OpportunityService::class)->moveToStage($opp, $stage, null, null, Auth::guard('tenant')->id());
            session()->now('success', 'Étape mise à jour.');
        } catch (\Throwable $e) {
            $this->boardMessage = $e->getMessage();
        }
    }

    public function openLostModal(int $id): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $this->modalOpportunityId = $id;
        $this->lostReason = '';
        $this->lostComment = '';
        $this->showLostModal = true;
    }

    public function closeLostModal(): void
    {
        $this->showLostModal = false;
        $this->modalOpportunityId = null;
    }

    public function saveLost(): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $this->validate([
            'lostReason' => 'required|string',
        ], [
            'lostReason.required' => 'Le motif de perte est obligatoire.',
        ]);
        if (! $this->modalOpportunityId) {
            return;
        }
        try {
            app(OpportunityService::class)->markLost(
                Opportunity::findOrFail($this->modalOpportunityId),
                $this->lostReason,
                $this->lostComment ?: null,
                Auth::guard('tenant')->id()
            );
            $this->closeLostModal();
            session()->now('success', 'Opportunité marquée perdue.');
        } catch (\Throwable $e) {
            session()->now('error', $e->getMessage());
        }
    }

    public function openScheduleModal(int $id, bool $keepPending = false): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        if (! $keepPending) {
            $this->pendingStage = null;
        }
        $this->modalOpportunityId = $id;
        $this->scheduleType = ProspectActivity::TYPE_CALL;
        $this->scheduleSummary = '';
        $this->scheduleDueAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->showScheduleModal = true;
        $this->showLostModal = false;
    }

    public function closeScheduleModal(): void
    {
        $this->showScheduleModal = false;
        $this->pendingStage = null;
        $this->boardMessage = null;
    }

    public function saveSchedule(): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        $this->validate([
            'scheduleDueAt' => 'required|date',
            'scheduleSummary' => 'required|string|max:180',
        ]);
        if (! $this->modalOpportunityId) {
            return;
        }
        try {
            app(OpportunityService::class)->scheduleNextAction(
                Opportunity::findOrFail($this->modalOpportunityId),
                [
                    'type' => $this->scheduleType,
                    'summary' => $this->scheduleSummary,
                    'due_at' => $this->scheduleDueAt,
                ],
                Auth::guard('tenant')->id()
            );
            $oppId = $this->modalOpportunityId;
            $pending = $this->pendingStage;
            $this->showScheduleModal = false;
            $this->modalOpportunityId = null;
            $this->pendingStage = null;
            $this->boardMessage = null;
            session()->now('success', 'Prochaine action planifiée.');
            if ($pending && $oppId) {
                $this->moveToStage($oppId, $pending);
            }
        } catch (\Throwable $e) {
            session()->now('error', $e->getMessage());
        }
    }

    public function transferToErp(int $id): void
    {
        $this->authorizeCrm('crm.opportunities.manage');
        try {
            $result = app(OpportunityService::class)->transferToQuotations(
                Opportunity::findOrFail($id),
                Auth::guard('tenant')->id()
            );
            session()->now('success', 'Contexte transmis au module Devis. Complétez le devis dans l’ERP.');
            $this->redirect($result['url'], navigate: true);
        } catch (\Throwable $e) {
            session()->now('error', $e->getMessage());
        }
    }

    private function resetCreateForm(): void
    {
        $this->createProspectSearch = '';
        $this->createProspectId = '';
        $this->createProspectLabel = '';
        $this->createProspectResults = [];
        $this->createTitle = '';
        $this->createAmount = '';
        $this->createCloseDate = '';
        $this->createOwnerId = '';
        $this->createNextSummary = '';
        $this->createNextDue = '';
        $this->createNextType = 'call';
    }

    public function render()
    {
        $this->authorizeCrm('crm.opportunities.view');
        $visibility = app(CrmVisibility::class);

        $columns = [];
        foreach (array_merge(Opportunity::pipelineStages(), [Opportunity::STAGE_GAGNE, Opportunity::STAGE_PERDU]) as $stage) {
            $columns[$stage] = [
                'label' => Opportunity::stageOptions()[$stage],
                'hint' => Opportunity::stageHints()[$stage],
                'tone' => Opportunity::stageTone($stage),
            ];
        }

        $query = $visibility->restrictOwner(
            Opportunity::query()->with(['prospect', 'owner', 'nextPlannedActivity'])
        )->orderByDesc('updated_at');

        if ($this->ownerFilter !== '') {
            $query->where('owner_id', (int) $this->ownerFilter);
        }

        $term = trim($this->boardSearch);
        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereHas('prospect', function ($pq) use ($like) {
                        $pq->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$like]);
                    });
            });
        }

        $all = $query->get();
        $items = $all->groupBy('stage');
        $open = $all->whereIn('stage', Opportunity::openStages());
        $pipelineValue = (float) $open->sum('amount');
        $weighted = (float) $open->sum(fn (Opportunity $o) => ((float) $o->amount) * ((int) $o->probability) / 100);
        $wonCount = $all->where('stage', Opportunity::STAGE_GAGNE)->count();
        $lostCount = $all->where('stage', Opportunity::STAGE_PERDU)->count();
        $closed = $wonCount + $lostCount;
        $conversion = $closed > 0 ? round($wonCount / $closed * 100, 1) : 0.0;

        $columnTotals = [];
        foreach (array_keys($columns) as $stage) {
            $col = $items[$stage] ?? collect();
            $columnTotals[$stage] = [
                'count' => $col->count(),
                'amount' => (float) $col->sum('amount'),
            ];
        }

        return view('inovcom-crm::livewire.opportunities', [
            'columns' => $columns,
            'items' => $items,
            'columnTotals' => $columnTotals,
            'pipelineValue' => $pipelineValue,
            'weighted' => $weighted,
            'conversion' => $conversion,
            'totalCount' => $all->count(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $this->canCrm('crm.opportunities.manage'),
            'actionTypes' => ProspectActivity::actionableTypeOptions(),
            'lostReasons' => Opportunity::lostReasonOptions(),
            'tenantCode' => $this->tenantCode(),
            'quotesEnabled' => Route::has('tenant.quotations.create'),
        ])->layout('layouts.app', [
            'title' => '',
            'subtitle' => '',
            'hidePageHeader' => true,
        ]);
    }
}
