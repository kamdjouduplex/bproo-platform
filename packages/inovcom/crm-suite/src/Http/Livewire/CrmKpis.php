<?php

namespace InovCom\Crm\Http\Livewire;

use InovCom\Clients\Models\Client;
use InovCom\Crm\Concerns\AuthorizesCrmActions;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;
use InovCom\Users\Models\User;
use Livewire\Component;

class CrmKpis extends Component
{
    use AuthorizesCrmActions;

    public string $period = '30'; // days

    public function render()
    {
        $this->authorizeCrm('crm.view');

        $days = max(7, min(365, (int) $this->period));
        $since = now()->subDays($days)->startOfDay();

        $summary = app(\InovCom\Prospects\Services\ProspectsService::class)->summarize();

        $pipelineValue = (float) Prospect::query()
            ->whereNotIn('status', [Prospect::STATUS_CONVERTI, Prospect::STATUS_PERDU])
            ->sum('expected_value');

        $wonValue = (float) Prospect::query()
            ->where('status', Prospect::STATUS_CONVERTI)
            ->sum('expected_value');

        $wonPeriod = Prospect::query()
            ->where('status', Prospect::STATUS_CONVERTI)
            ->where('converted_at', '>=', $since)
            ->get(['id', 'owner_id', 'expected_value', 'converted_at', 'created_at']);

        $wonValuePeriod = (float) $wonPeriod->sum('expected_value');

        $activitiesWeek = ProspectActivity::query()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $plannedOpen = ProspectActivity::query()
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->count();

        $overdue = ProspectActivity::query()
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $clientsCount = class_exists(Client::class) ? Client::query()->count() : 0;

        $bySource = $summary['by_source'] ?? [];

        // —— Per commercial performance ——
        $owners = User::query()->orderBy('name')->get(['id', 'name']);
        $repStats = [];

        foreach ($owners as $owner) {
            $open = Prospect::query()
                ->where('owner_id', $owner->id)
                ->whereNotIn('status', [Prospect::STATUS_CONVERTI, Prospect::STATUS_PERDU]);

            $openCount = (clone $open)->count();
            $openValue = (float) (clone $open)->sum('expected_value');

            $converted = Prospect::query()
                ->where('owner_id', $owner->id)
                ->where('status', Prospect::STATUS_CONVERTI)
                ->where('converted_at', '>=', $since)
                ->count();

            $convertedValue = (float) Prospect::query()
                ->where('owner_id', $owner->id)
                ->where('status', Prospect::STATUS_CONVERTI)
                ->where('converted_at', '>=', $since)
                ->sum('expected_value');

            $lost = Prospect::query()
                ->where('owner_id', $owner->id)
                ->where('status', Prospect::STATUS_PERDU)
                ->where('updated_at', '>=', $since)
                ->count();

            $closed = $converted + $lost;
            $winRate = $closed > 0 ? round(($converted / $closed) * 100, 1) : null;

            $actsDone = ProspectActivity::query()
                ->where(function ($q) use ($owner) {
                    $q->where('assignee_id', $owner->id)
                        ->orWhere('user_id', $owner->id);
                })
                ->where('state', ProspectActivity::STATE_DONE)
                ->where('completed_at', '>=', $since)
                ->count();

            $actsPlanned = ProspectActivity::query()
                ->where('assignee_id', $owner->id)
                ->where('state', ProspectActivity::STATE_PLANNED)
                ->count();

            $actsOverdue = ProspectActivity::query()
                ->where('assignee_id', $owner->id)
                ->where('state', ProspectActivity::STATE_PLANNED)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count();

            $unassignedActions = 0; // not per-rep

            // Skip inactive reps with zero footprint
            if ($openCount === 0 && $converted === 0 && $lost === 0 && $actsDone === 0 && $actsPlanned === 0) {
                continue;
            }

            $repStats[] = [
                'id' => $owner->id,
                'name' => $owner->name,
                'open_count' => $openCount,
                'open_value' => $openValue,
                'converted' => $converted,
                'converted_value' => $convertedValue,
                'lost' => $lost,
                'win_rate' => $winRate,
                'acts_done' => $actsDone,
                'acts_planned' => $actsPlanned,
                'acts_overdue' => $actsOverdue,
            ];
        }

        // Unassigned pipeline
        $unassignedOpen = Prospect::query()
            ->whereNull('owner_id')
            ->whereNotIn('status', [Prospect::STATUS_CONVERTI, Prospect::STATUS_PERDU])
            ->count();

        usort($repStats, fn ($a, $b) => $b['converted_value'] <=> $a['converted_value']);

        $recent = Prospect::query()
            ->with(['owner', 'nextPlannedActivity'])
            ->whereNotIn('status', [Prospect::STATUS_CONVERTI, Prospect::STATUS_PERDU])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('inovcom-crm::livewire.kpis', [
            'summary' => $summary,
            'pipelineValue' => $pipelineValue,
            'wonValue' => $wonValue,
            'wonValuePeriod' => $wonValuePeriod,
            'convertedPeriod' => $wonPeriod->count(),
            'activitiesWeek' => $activitiesWeek,
            'plannedOpen' => $plannedOpen,
            'overdue' => $overdue,
            'clientsCount' => $clientsCount,
            'recent' => $recent,
            'bySource' => $bySource,
            'repStats' => $repStats,
            'unassignedOpen' => $unassignedOpen,
            'period' => (string) $days,
            'canCreate' => $this->canCrm('crm.prospects.create'),
            'canViewOpp' => $this->canCrm('crm.opportunities.view'),
            'canViewAct' => $this->canCrm('crm.activities.view'),
        ])->layout('layouts.app', [
            'title' => 'KPI CRM',
            'subtitle' => 'Performance commerciale',
        ]);
    }
}
