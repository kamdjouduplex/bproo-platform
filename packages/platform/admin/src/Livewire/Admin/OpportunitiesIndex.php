<?php

namespace App\Livewire\Admin;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use Livewire\Component;

/**
 * Commercial pipeline kanban — Qualifié → Proposition → Négociation → À convertir.
 */
class OpportunitiesIndex extends Component
{
    public bool $embedded = false;

    public string $search = '';
    public string $stage = '';
    public string $product = '';
    public string $owner = '';

    public function advance(int $id): void
    {
        $prospect = PlatformProspect::find($id);
        if (! $prospect || $prospect->converted_tenant_id) {
            return;
        }

        $next = PlatformProspect::nextOpportunityStage($prospect->stage);
        if (! $next) {
            if ($prospect->stage !== PlatformProspect::STAGE_WON) {
                $this->applyStage($prospect, PlatformProspect::STAGE_WON, silent: true);
            }
            $this->redirect(route('system.tenants.create', ['prospect' => $prospect->id]), navigate: true);

            return;
        }

        $this->applyStage($prospect, $next);
    }

    public function markLost(int $id): void
    {
        $prospect = PlatformProspect::find($id);
        if (! $prospect || $prospect->converted_tenant_id) {
            return;
        }
        $this->applyStage($prospect, PlatformProspect::STAGE_LOST);
        notify()->success('Marqué perdu.');
    }

    public function assignOwner(int $id, $userId = null): void
    {
        $prospect = PlatformProspect::find($id);
        if (! $prospect || $prospect->converted_tenant_id) {
            return;
        }

        $uid = $userId === '' || $userId === null ? null : (int) $userId;
        if ($prospect->assignOwner($uid, auth()->id())) {
            notify()->success('Commercial mis à jour.');
        }
    }

    private function applyStage(PlatformProspect $prospect, string $stage, bool $silent = false): void
    {
        $old = $prospect->stage;
        if ($old === $stage) {
            return;
        }

        $prospect->update([
            'stage' => $stage,
            'probability' => PlatformProspect::defaultProbabilityForStage($stage),
        ]);

        PlatformProspectActivity::create([
            'platform_prospect_id' => $prospect->id,
            'user_id' => auth()->id(),
            'type' => 'stage_change',
            'subject' => 'Pipeline',
            'body' => (PlatformProspect::stages()[$old] ?? $old) . ' → ' . (PlatformProspect::stages()[$stage] ?? $stage),
        ]);

        if (! $silent) {
            notify()->success('Étape mise à jour.');
        }
    }

    public function render()
    {
        $oppStages = array_keys(PlatformProspect::opportunityStages());

        $openQuery = PlatformProspect::query()
            ->with(['owner'])
            ->whereNull('converted_tenant_id')
            ->whereIn('stage', $oppStages)
            ->orderByDesc('expected_value')
            ->orderBy('next_follow_up_at');

        if (trim($this->search) !== '') {
            $term = '%' . strtolower(trim($this->search)) . '%';
            $openQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(company_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contact_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(contact_email) LIKE ?', [$term]);
            });
        }
        if ($this->product !== '') {
            $openQuery->where('product_interest', $this->product);
        }
        if ($this->stage !== '' && in_array($this->stage, $oppStages, true)) {
            $openQuery->where('stage', $this->stage);
        }
        if ($this->owner === 'none') {
            $openQuery->whereNull('owner_user_id');
        } elseif ($this->owner !== '') {
            $openQuery->where('owner_user_id', (int) $this->owner);
        }

        $opportunities = $openQuery->limit(300)->get();
        $byStage = [];
        foreach ($oppStages as $s) {
            $byStage[$s] = $opportunities->where('stage', $s)->values();
        }

        $openOnly = $opportunities->where('stage', '!=', PlatformProspect::STAGE_WON);
        $weighted = (float) $openOnly->sum(fn (PlatformProspect $p) => $p->weightedValue());
        $pipelineValue = (float) $openOnly->sum(fn (PlatformProspect $p) => (float) ($p->expected_value ?? 0));

        $view = view('livewire.admin.opportunities-index', [
            'opportunities' => $opportunities,
            'byStage' => $byStage,
            'oppStages' => PlatformProspect::opportunityStages(),
            'productTypes' => config('tenant_types.types', []),
            'salespeople' => PlatformProspect::salespeople(),
            'kpis' => [
                'count' => $openOnly->count(),
                'pipeline' => $pipelineValue,
                'weighted' => $weighted,
                'to_convert' => $byStage[PlatformProspect::STAGE_WON]->count(),
                'overdue' => $opportunities->filter(fn ($p) => $p->next_follow_up_at && $p->next_follow_up_at->isPast())->count(),
            ],
            'embedded' => $this->embedded,
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'Opportunités',
            'subtitle' => 'Pipeline commercial',
        ]);
    }
}
