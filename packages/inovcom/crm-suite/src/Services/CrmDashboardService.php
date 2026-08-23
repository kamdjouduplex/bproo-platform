<?php

namespace InovCom\Crm\Services;

use InovCom\Crm\Models\Opportunity;
use InovCom\Crm\Support\CrmVisibility;
use InovCom\Prospects\Models\Prospect;
use InovCom\Prospects\Models\ProspectActivity;

class CrmDashboardService
{
    public function __construct(private CrmVisibility $visibility) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $periodDays = 30): array
    {
        $since = now()->subDays(max(7, min(365, $periodDays)))->startOfDay();
        $prevSince = $since->copy()->subDays($periodDays);

        $prospects = $this->visibility->restrictOwner(Prospect::query());
        $opps = $this->visibility->restrictOwner(Opportunity::query());
        $acts = ProspectActivity::query()->whereHas('prospect', function ($q) {
            $this->visibility->restrictOwner($q);
        });

        $openOpps = (clone $opps)->whereIn('stage', Opportunity::openStages());
        $openCount = (clone $openOpps)->count();
        $pipelineValue = (float) (clone $openOpps)->sum('amount');
        $hotOpps = (clone $openOpps)->where('probability', '>=', 61)->count();

        $won = (clone $opps)->where('stage', Opportunity::STAGE_GAGNE)->where('won_at', '>=', $since)->count();
        $lost = (clone $opps)->where('stage', Opportunity::STAGE_PERDU)->where('lost_at', '>=', $since)->count();
        $wonPrev = (clone $opps)->where('stage', Opportunity::STAGE_GAGNE)->whereBetween('won_at', [$prevSince, $since])->count();
        $lostPrev = (clone $opps)->where('stage', Opportunity::STAGE_PERDU)->whereBetween('lost_at', [$prevSince, $since])->count();

        $newProspects = (clone $prospects)->where('created_at', '>=', $since)->count();
        $newPrev = (clone $prospects)->whereBetween('created_at', [$prevSince, $since])->count();
        $qualified = (clone $prospects)->where('status', Prospect::STATUS_QUALIFIE)->where('updated_at', '>=', $since)->count();

        $openPrev = (clone $opps)->whereIn('stage', Opportunity::openStages())->where('created_at', '<', $since)->count();
        $pipelinePrev = (float) (clone $opps)->whereIn('stage', Opportunity::openStages())->where('created_at', '<', $since)->sum('amount');

        $closed = $won + $lost;
        $conversion = $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0;

        $planned = (clone $acts)->where('state', ProspectActivity::STATE_PLANNED);
        $overdue = (clone $planned)->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $today = (clone $planned)->whereNotNull('due_at')->whereDate('due_at', now()->toDateString())->count();
        $upcoming = (clone $planned)->whereNotNull('due_at')->where('due_at', '>', now()->endOfDay())->count();

        $todayActivities = (clone $acts)
            ->with(['prospect', 'assignee'])
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->whereNotNull('due_at')
            ->whereDate('due_at', now()->toDateString())
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $nextActivities = (clone $acts)
            ->with(['prospect', 'assignee'])
            ->where('state', ProspectActivity::STATE_PLANNED)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $stale = (clone $openOpps)
            ->with('prospect')
            ->where(function ($q) {
                $q->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', now()->subDays(7));
            })
            ->orderBy('last_activity_at')
            ->limit(6)
            ->get();

        $pipelineByStage = [];
        foreach (Opportunity::pipelineStages() as $stage) {
            $q = (clone $opps)->where('stage', $stage);
            $pipelineByStage[] = [
                'stage' => $stage,
                'label' => Opportunity::stageOptions()[$stage],
                'tone' => Opportunity::stageTone($stage),
                'count' => (clone $q)->count(),
                'amount' => (float) (clone $q)->sum('amount'),
            ];
        }

        $bySource = [];
        $srcTotal = (clone $prospects)->count();
        foreach (Prospect::sourceOptions() as $src => $label) {
            $count = (clone $prospects)->where('source', $src)->count();
            if ($count === 0) {
                continue;
            }
            $wonSrc = Opportunity::query()
                ->where('stage', Opportunity::STAGE_GAGNE)
                ->whereHas('prospect', fn ($q) => $q->where('source', $src))
                ->count();
            $bySource[] = [
                'source' => $src,
                'label' => $label,
                'total' => $count,
                'won' => $wonSrc,
                'share' => $srcTotal > 0 ? round($count / $srcTotal * 100) : 0,
            ];
        }
        usort($bySource, fn ($a, $b) => $b['total'] <=> $a['total']);

        $activityMix = [];
        foreach (ProspectActivity::actionableTypeOptions() as $type => $label) {
            $count = (clone $acts)->where('type', $type)->where('created_at', '>=', $since)->count();
            if ($count > 0) {
                $activityMix[] = ['type' => $type, 'label' => $label, 'count' => $count];
            }
        }

        return [
            'kpis' => [
                'new_prospects' => $newProspects,
                'new_prospects_delta' => $this->deltaPct($newProspects, $newPrev),
                'qualified' => $qualified,
                'open' => $openCount,
                'open_delta' => $this->deltaPct($openCount, $openPrev),
                'pipeline_value' => $pipelineValue,
                'pipeline_delta' => $this->deltaPct($pipelineValue, $pipelinePrev),
                'won' => $won,
                'won_delta' => $this->deltaPct($won, $wonPrev),
                'lost' => $lost,
                'lost_delta' => $this->deltaPct($lost, $lostPrev),
                'hot' => $hotOpps,
                'conversion' => $conversion,
            ],
            'actions' => [
                'overdue' => $overdue,
                'today' => $today,
                'upcoming' => $upcoming,
            ],
            'today_agenda' => $todayActivities,
            'next_activities' => $nextActivities,
            'stale' => $stale,
            'pipeline_by_stage' => $pipelineByStage,
            'by_source' => array_slice($bySource, 0, 6),
            'activity_mix' => $activityMix,
        ];
    }

    private function deltaPct(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
