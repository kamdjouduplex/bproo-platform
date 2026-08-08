<?php

namespace App\Livewire\Admin;

use App\Models\PlatformProspect;
use App\Models\PlatformProspectActivity;
use Livewire\Component;
use Livewire\WithPagination;

class ProspectsIndex extends Component
{
    use WithPagination;

    public bool $embedded = false;

    public string $search = '';
    public string $stage = 'lead';
    public string $owner = '';
    public string $product = '';
    public string $country = '';
    public string $city = '';

    protected $paginationTheme = 'cc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStage(): void
    {
        $this->resetPage();
    }

    public function updatingOwner(): void
    {
        $this->resetPage();
    }

    public function updatingProduct(): void
    {
        $this->resetPage();
    }

    public function updatingCountry(): void
    {
        $this->city = '';
        $this->resetPage();
    }

    public function updatingCity(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $prospect = PlatformProspect::find($id);
        if ($prospect && ! $prospect->converted_tenant_id) {
            $prospect->activities()->delete();
            $prospect->delete();
            notify()->success('Supprimé.');
        }
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

    public function markLost(int $id): void
    {
        $prospect = PlatformProspect::find($id);
        if (! $prospect || $prospect->converted_tenant_id) {
            return;
        }
        $old = $prospect->stage;
        $prospect->update([
            'stage' => PlatformProspect::STAGE_LOST,
            'probability' => 0,
        ]);
        PlatformProspectActivity::create([
            'platform_prospect_id' => $prospect->id,
            'user_id' => auth()->id(),
            'type' => 'stage_change',
            'subject' => 'Perdu',
            'body' => (PlatformProspect::stages()[$old] ?? $old) . ' → Perdu',
        ]);
        notify()->success('Marqué perdu.');
    }

    public function promoteToOpportunity(int $id): void
    {
        $prospect = PlatformProspect::find($id);
        if (! $prospect || $prospect->converted_tenant_id) {
            return;
        }
        if (array_key_exists($prospect->stage, PlatformProspect::opportunityStages())
            && $prospect->stage !== PlatformProspect::STAGE_WON) {
            notify()->warning('Déjà en opportunité.');

            return;
        }

        if (! $prospect->owner_user_id) {
            $prospect->assignOwner(auth()->id(), auth()->id());
        }

        $prospect->update([
            'stage' => PlatformProspect::STAGE_QUALIFIED,
            'probability' => PlatformProspect::defaultProbabilityForStage(PlatformProspect::STAGE_QUALIFIED),
        ]);

        PlatformProspectActivity::create([
            'platform_prospect_id' => $prospect->id,
            'user_id' => auth()->id(),
            'type' => 'stage_change',
            'subject' => 'Opportunité',
            'body' => 'Promu en opportunité.',
        ]);

        notify()->success('Ajouté au pipeline.');
        $this->redirect(route('system.opportunities'), navigate: true);
    }

    public function render()
    {
        $query = PlatformProspect::query()
            ->with(['convertedTenant', 'owner'])
            ->orderByDesc('updated_at');

        if (trim($this->search) !== '') {
            $term = '%' . strtolower(trim($this->search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(company_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(contact_name, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(contact_email, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(contact_phone, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(country, \'\')) LIKE ?', [$term]);
            });
        }
        if ($this->stage !== '') {
            $query->where('stage', $this->stage);
        }
        if ($this->owner === 'none') {
            $query->whereNull('owner_user_id');
        } elseif ($this->owner !== '') {
            $query->where('owner_user_id', (int) $this->owner);
        }
        if ($this->product !== '') {
            $query->where('product_interest', $this->product);
        }
        if ($this->country !== '') {
            $query->where('country', $this->country);
        }
        if ($this->city !== '') {
            $query->where('city', $this->city);
        }

        $countries = PlatformProspect::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $citiesQuery = PlatformProspect::query()
            ->whereNotNull('city')
            ->where('city', '!=', '');
        if ($this->country !== '') {
            $citiesQuery->where('country', $this->country);
        }
        $cities = $citiesQuery->distinct()->orderBy('city')->pluck('city');

        $followUps = PlatformProspect::query()
            ->whereNull('converted_tenant_id')
            ->where('stage', PlatformProspect::STAGE_LEAD)
            ->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', '<=', now()->toDateString())
            ->count();

        $view = view('livewire.admin.prospects-index', [
            'prospects' => $query->paginate(25),
            'stages' => PlatformProspect::stages(),
            'salespeople' => PlatformProspect::salespeople(),
            'productTypes' => config('tenant_types.types', []),
            'countries' => $countries,
            'cities' => $cities,
            'kpis' => [
                'leads' => PlatformProspect::where('stage', PlatformProspect::STAGE_LEAD)->whereNull('converted_tenant_id')->count(),
                'follow_ups' => $followUps,
                'won_pending' => PlatformProspect::where('stage', PlatformProspect::STAGE_WON)->whereNull('converted_tenant_id')->count(),
                'unassigned' => PlatformProspect::whereNull('converted_tenant_id')
                    ->whereNull('owner_user_id')
                    ->whereNotIn('stage', [PlatformProspect::STAGE_LOST])
                    ->count(),
            ],
            'embedded' => $this->embedded,
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'Prospects',
            'subtitle' => 'Nouveaux contacts',
        ]);
    }
}
