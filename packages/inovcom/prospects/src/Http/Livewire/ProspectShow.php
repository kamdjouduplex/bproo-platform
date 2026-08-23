<?php

namespace InovCom\Prospects\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use InovCom\Prospects\Concerns\AuthorizesProspectActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Prospects\Services\ProspectsService;
use InovCom\Users\Models\User;
use Livewire\Component;

class ProspectShow extends Component
{
    use AuthorizesProspectActions;

    public Prospect $prospect;

    public string $newStatus = '';

    public string $lostReason = '';

    public string $activityType = 'note';

    public string $activityBody = '';

    public bool $activityIsPlanned = false;

    public string $activityDueAt = '';

    public bool $createQuotationAfterConvert = false;

    public bool $showPanelActions = false;

    public bool $showAssignModal = false;

    public bool $showScheduleModal = false;

    public string $assignOwnerId = '';

    public string $scheduleType = 'call';

    public string $scheduleSummary = '';

    public string $scheduleBody = '';

    public string $scheduleDueAt = '';

    public string $scheduleAssigneeId = '';

    public string $tab = 'resume';

    public bool $showConvertOppModal = false;

    public string $oppTitle = '';

    public string $oppAmount = '';

    public string $oppCloseDate = '';

    public string $oppNextSummary = '';

    public string $oppNextDue = '';

    public int $needScore = 0;

    public int $decisionScore = 0;

    public int $budgetScore = 0;

    public int $timelineScore = 0;

    public string $problem = '';

    public string $expectations = '';

    public string $productInterest = '';

    public string $decisionMakerName = '';

    public string $needText = '';

    public function mount(Prospect $prospect): void
    {
        $this->authorizeProspectAction('prospects.view');
        $this->prospect = $prospect->load([
            'owner',
            'creator',
            'convertedClient',
            'nextPlannedActivity.assignee',
            'primaryOpportunity.nextPlannedActivity',
            'opportunities',
            'activities' => fn ($q) => $q->with(['user', 'assignee'])->orderByDesc('created_at')->limit(40),
        ]);
        $this->newStatus = $prospect->status;
        $this->tab = request()->query('tab', 'resume');
        $this->syncQualificationFields();
    }

    public function togglePanelActions(): void
    {
        $this->showPanelActions = ! $this->showPanelActions;
    }

    public function refreshProspect(): void
    {
        $this->prospect = $this->prospect->fresh([
            'owner',
            'creator',
            'convertedClient',
            'nextPlannedActivity.assignee',
            'primaryOpportunity.nextPlannedActivity',
            'opportunities',
            'activities' => fn ($q) => $q->with(['user', 'assignee'])->orderByDesc('created_at')->limit(40),
        ]);
        $this->newStatus = $this->prospect->status;
        $this->showPanelActions = false;
        $this->syncQualificationFields();
    }

    private function syncQualificationFields(): void
    {
        $this->needScore = (int) ($this->prospect->need_score ?? 0);
        $this->decisionScore = (int) ($this->prospect->decision_score ?? 0);
        $this->budgetScore = (int) ($this->prospect->budget_score ?? 0);
        $this->timelineScore = (int) ($this->prospect->timeline_score ?? 0);
        $this->problem = (string) ($this->prospect->problem ?? '');
        $this->expectations = (string) ($this->prospect->expectations ?? '');
        $this->productInterest = (string) ($this->prospect->product_interest ?? '');
        $this->decisionMakerName = (string) ($this->prospect->decision_maker_name ?? '');
        $this->needText = (string) ($this->prospect->need ?? '');
    }

    public function changeStatus(): void
    {
        $this->authorizeProspectAction('prospects.update');

        try {
            $this->prospect = app(ProspectsService::class)->changeStatus(
                $this->prospect,
                $this->newStatus,
                $this->lostReason ?: null,
                null,
                Auth::guard('tenant')->id()
            );
            $this->lostReason = '';
            $this->refreshProspect();
            session()->flash('success', 'Statut mis à jour.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addActivity(): void
    {
        $this->authorizeProspectAction('prospects.update');

        if ($this->activityIsPlanned) {
            $this->validate([
                'activityType' => 'required|in:'.ProspectActivity::actionableTypeKeys(),
                'activityDueAt' => 'required|date',
                'activityBody' => 'nullable|string|max:5000',
            ]);

            try {
                app(ProspectsService::class)->scheduleActivity(
                    $this->prospect,
                    [
                        'type' => $this->activityType,
                        'body' => $this->activityBody,
                        'summary' => null,
                        'due_at' => $this->activityDueAt,
                        'assignee_id' => $this->prospect->owner_id,
                    ],
                    Auth::guard('tenant')->id()
                );
                $this->activityBody = '';
                $this->activityDueAt = '';
                $this->activityIsPlanned = false;
                $this->activityType = 'call';
                $this->refreshProspect();
                session()->flash('success', 'Action planifiée.');
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());
            }

            return;
        }

        $this->validate([
            'activityType' => 'required|in:'.ProspectActivity::actionableTypeKeys(),
            'activityBody' => 'required|string|max:5000',
        ]);

        try {
            app(ProspectsService::class)->addActivity(
                $this->prospect,
                $this->activityType,
                $this->activityBody,
                Auth::guard('tenant')->id()
            );
            $this->activityBody = '';
            $this->activityType = 'note';
            $this->refreshProspect();
            session()->flash('success', 'Activité ajoutée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeActivity(int $activityId): void
    {
        $this->completeNextAction($activityId);
    }

    public function completeNextAction(int $activityId): void
    {
        $this->authorizeProspectAction('prospects.update');
        try {
            app(ProspectsService::class)->completeActivity(
                ProspectActivity::where('prospect_id', $this->prospect->id)->findOrFail($activityId),
                null,
                Auth::guard('tenant')->id()
            );
            $this->refreshProspect();
            $this->scheduleType = ProspectActivity::TYPE_FOLLOWUP;
            $this->scheduleSummary = '';
            $this->scheduleDueAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
            $this->showScheduleModal = true;
            session()->flash('success', 'Action terminée. Définissez la prochaine action.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function initiate(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.update');
        if ($prospectId !== $this->prospect->id) {
            return;
        }
        try {
            app(ProspectsService::class)->initiateAsProspect(
                $this->prospect,
                Auth::guard('tenant')->id()
            );
            $this->refreshProspect();
            session()->flash('success', 'Lead initié en prospect.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function advance(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.update');
        if ($prospectId !== $this->prospect->id) {
            return;
        }

        $order = Prospect::pipelineStatuses();
        $idx = array_search($this->prospect->status, $order, true);
        if ($idx === false || $idx >= count($order) - 1) {
            return;
        }

        try {
            app(ProspectsService::class)->changeStatus(
                $this->prospect,
                $order[$idx + 1],
                null,
                null,
                Auth::guard('tenant')->id()
            );
            $this->refreshProspect();
            session()->flash('success', 'Étape mise à jour : '.Prospect::statusLabel($order[$idx + 1]).'.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markLost(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.update');
        if ($prospectId !== $this->prospect->id || $this->prospect->isConverted() || $this->prospect->isLost()) {
            return;
        }
        try {
            app(ProspectsService::class)->changeStatus(
                $this->prospect,
                Prospect::STATUS_PERDU,
                'Perdu depuis la fiche prospect',
                null,
                Auth::guard('tenant')->id()
            );
            $this->refreshProspect();
            session()->flash('success', 'Prospect marqué perdu.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openAssignModal(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.update');
        if ($prospectId !== $this->prospect->id) {
            return;
        }
        $this->assignOwnerId = $this->prospect->owner_id ? (string) $this->prospect->owner_id : '';
        $this->showAssignModal = true;
        $this->showScheduleModal = false;
        $this->showPanelActions = false;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
    }

    public function saveAssign(): void
    {
        $this->authorizeProspectAction('prospects.update');
        try {
            app(ProspectsService::class)->assignOwner(
                $this->prospect,
                $this->assignOwnerId !== '' ? (int) $this->assignOwnerId : null,
                Auth::guard('tenant')->id()
            );
            $this->closeAssignModal();
            $this->refreshProspect();
            session()->flash('success', 'Commercial assigné.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openScheduleModal(int $prospectId): void
    {
        $this->authorizeProspectAction('prospects.update');
        if ($prospectId !== $this->prospect->id) {
            return;
        }
        $this->scheduleType = ProspectActivity::TYPE_CALL;
        $this->scheduleSummary = '';
        $this->scheduleBody = '';
        $this->scheduleDueAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->scheduleAssigneeId = $this->prospect->owner_id
            ? (string) $this->prospect->owner_id
            : (string) (Auth::guard('tenant')->id() ?? '');
        $this->showScheduleModal = true;
        $this->showAssignModal = false;
        $this->showPanelActions = false;
    }

    public function closeScheduleModal(): void
    {
        $this->showScheduleModal = false;
    }

    public function saveSchedule(): void
    {
        $this->authorizeProspectAction('prospects.update');
        $this->validate([
            'scheduleType' => 'required|in:'.ProspectActivity::actionableTypeKeys(),
            'scheduleDueAt' => 'required|date',
            'scheduleSummary' => 'nullable|string|max:180',
            'scheduleBody' => 'nullable|string|max:5000',
            'scheduleAssigneeId' => 'nullable',
        ]);

        try {
            app(ProspectsService::class)->scheduleActivity(
                $this->prospect,
                [
                    'type' => $this->scheduleType,
                    'summary' => $this->scheduleSummary,
                    'body' => $this->scheduleBody,
                    'due_at' => $this->scheduleDueAt,
                    'assignee_id' => $this->scheduleAssigneeId !== '' ? (int) $this->scheduleAssigneeId : null,
                ],
                Auth::guard('tenant')->id()
            );
            $this->closeScheduleModal();
            $this->refreshProspect();
            session()->flash('success', 'Prochaine action planifiée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function logQuickActivity(int $prospectId, string $type): void
    {
        $this->authorizeProspectAction('prospects.update');
        if ($prospectId !== $this->prospect->id) {
            return;
        }
        $labels = [
            ProspectActivity::TYPE_CALL => 'Appel effectué.',
            ProspectActivity::TYPE_EMAIL => 'E-mail envoyé.',
            ProspectActivity::TYPE_NOTE => 'Note ajoutée.',
        ];
        if (! isset($labels[$type])) {
            return;
        }
        try {
            app(ProspectsService::class)->addActivity(
                $this->prospect,
                $type,
                $labels[$type],
                Auth::guard('tenant')->id()
            );
            $this->refreshProspect();
            session()->flash('success', $labels[$type]);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function setTab(string $tab): void
    {
        $allowed = ['resume', 'besoin', 'timeline', 'activites', 'opportunites', 'fichiers', 'notes'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'resume';
    }

    public function saveQualification(): void
    {
        $this->authorizeProspectAction('prospects.update');
        try {
            $this->prospect = app(ProspectsService::class)->update($this->prospect, [
                'name' => $this->prospect->name,
                'type' => $this->prospect->type,
                'email' => $this->prospect->email,
                'phone' => $this->prospect->phone,
                'address' => $this->prospect->address,
                'tax_id' => $this->prospect->tax_id,
                'rccm' => $this->prospect->rccm,
                'niu' => $this->prospect->niu,
                'source' => $this->prospect->source,
                'owner_id' => $this->prospect->owner_id,
                'notes' => $this->prospect->notes,
                'need' => $this->needText,
                'product_interest' => $this->productInterest,
                'problem' => $this->problem,
                'expectations' => $this->expectations,
                'decision_maker_name' => $this->decisionMakerName,
                'need_score' => $this->needScore,
                'decision_score' => $this->decisionScore,
                'budget_score' => $this->budgetScore,
                'timeline_score' => $this->timelineScore,
            ], Auth::guard('tenant')->id());

            if (class_exists(\InovCom\Crm\Services\ProspectScoringService::class)) {
                app(\InovCom\Crm\Services\ProspectScoringService::class)->recalculate($this->prospect);
            }
            if (in_array($this->prospect->status, [Prospect::STATUS_NOUVEAU, Prospect::STATUS_A_QUALIFIER], true)
                && $this->needScore >= 10) {
                app(ProspectsService::class)->changeStatus(
                    $this->prospect,
                    Prospect::STATUS_QUALIFIE,
                    null,
                    'Qualification enregistrée.',
                    Auth::guard('tenant')->id()
                );
            }
            $this->refreshProspect();
            session()->flash('success', 'Qualification mise à jour. Score recalculé.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openConvertOppModal(): void
    {
        $this->authorizeProspectAction('prospects.update');
        $this->oppTitle = $this->prospect->need ?: $this->prospect->product_interest ?: ('Opportunité '.$this->prospect->name);
        $this->oppAmount = $this->prospect->expected_value !== null ? (string) $this->prospect->expected_value : '';
        $this->oppCloseDate = $this->prospect->decision_deadline?->format('Y-m-d') ?? '';
        $this->oppNextSummary = 'Appeler '.$this->prospect->contactName();
        $this->oppNextDue = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->showConvertOppModal = true;
    }

    public function convertToOpportunity(): void
    {
        $this->authorizeProspectAction('prospects.update');
        $this->validate([
            'oppTitle' => 'required|string|max:180',
            'oppNextSummary' => 'required|string|max:180',
            'oppNextDue' => 'required|date',
        ]);
        try {
            $opp = app(\InovCom\Crm\Services\OpportunityService::class)->createFromProspect(
                $this->prospect,
                [
                    'title' => $this->oppTitle,
                    'amount' => $this->oppAmount !== '' ? $this->oppAmount : null,
                    'owner_id' => $this->prospect->owner_id ?: Auth::guard('tenant')->id(),
                    'expected_close_date' => $this->oppCloseDate ?: null,
                    'next_action' => [
                        'type' => ProspectActivity::TYPE_CALL,
                        'summary' => $this->oppNextSummary,
                        'due_at' => $this->oppNextDue,
                    ],
                ],
                Auth::guard('tenant')->id()
            );
            $this->showConvertOppModal = false;
            $this->refreshProspect();
            session()->flash('success', 'Opportunité créée : '.$opp->title);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function transferToErp(): void
    {
        $this->authorizeProspectAction('prospects.update');
        $opp = $this->prospect->primaryOpportunity;
        if (! $opp) {
            session()->flash('error', 'Créez d’abord une opportunité.');

            return;
        }
        try {
            $result = app(\InovCom\Crm\Services\OpportunityService::class)->transferToQuotations(
                $opp,
                Auth::guard('tenant')->id()
            );
            session()->flash('success', 'Contexte transmis au module Devis.');
            $this->redirect($result['url'], navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function convert(?int $prospectId = null): void
    {
        $this->authorizeProspectAction('prospects.convert');
        if ($prospectId !== null && $prospectId !== $this->prospect->id) {
            return;
        }

        try {
            $result = app(ProspectsService::class)->convertToClient(
                $this->prospect,
                Auth::guard('tenant')->id()
            );
            $client = $result['client'];
            $this->prospect = $result['prospect'];

            session()->flash('success', 'Prospect converti en client '.$client->code.'.');

            if ($this->createQuotationAfterConvert && Route::has('tenant.quotations.create')) {
                $this->redirect(route('tenant.quotations.create', [
                    'tenant' => $this->tenantCode(),
                    'client_id' => $client->id,
                ]), navigate: true);

                return;
            }

            if (Route::has('tenant.clients.show')) {
                $this->redirect(route('tenant.clients.show', [
                    'client' => $client->id,
                    'tenant' => $this->tenantCode(),
                ]), navigate: true);
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function delete(): void
    {
        $this->authorizeProspectAction('prospects.delete');

        try {
            app(ProspectsService::class)->delete($this->prospect);
            session()->flash('success', 'Prospect supprimé.');
            $this->redirect(route('tenant.prospects.index', [
                'tenant' => $this->tenantCode(),
            ]), navigate: true);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $tenant = request()->attributes->get('tenant');
        $crmEnabled = class_exists(\App\Services\ModuleRegistry::class)
            && $tenant
            && app(\App\Services\ModuleRegistry::class)->isEnabled('crm', $tenant);

        return view('inovcom-prospects::livewire.prospects.show', [
            'tenantCode' => $this->tenantCode(),
            'canUpdate' => $this->canProspect('prospects.update') && $this->prospect->isEditable(),
            'canConvert' => $this->canProspect('prospects.convert') && $this->prospect->canConvert(),
            'conversionGaps' => $this->prospect->conversionGaps(),
            'readyToConvert' => $this->prospect->isReadyToConvert(),
            'canDelete' => $this->canProspect('prospects.delete') && ! $this->prospect->isConverted(),
            'activityTypes' => ProspectActivity::actionableTypeOptions(),
            'actionTypes' => ProspectActivity::actionableTypeOptions(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
            'crmEnabled' => $crmEnabled,
            'scoring' => class_exists(\InovCom\Crm\Services\ProspectScoringService::class)
                ? \InovCom\Crm\Services\ProspectScoringService::class
                : null,
        ])->layout('layouts.app', [
            'title' => '',
            'subtitle' => '',
            'hidePageHeader' => true,
        ]);
    }
}
