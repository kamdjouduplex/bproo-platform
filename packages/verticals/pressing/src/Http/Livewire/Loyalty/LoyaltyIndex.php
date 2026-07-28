<?php

namespace Pressing\Http\Livewire\Loyalty;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\PressingClient;
use Pressing\Models\PressingLoyaltyReward;
use Pressing\Services\PressingLoyaltyService;
use Pressing\Support\PressingSettings;

class LoyaltyIndex extends Component
{
    use AuthorizesPressingActions;
    use WithPagination;

    public string $search = '';

    public ?int $agenceFilter = null;

    public string $tab = 'clients'; // clients | rewards

    public ?int $detailClientId = null;

    public ?int $adjustClientId = null;

    public string $adjust_points = '';

    public string $adjust_reason = '';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_loyalty.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAgenceFilter(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['clients', 'rewards'], true) ? $tab : 'clients';
        $this->resetPage();
    }

    public function showDetail(int $clientId): void
    {
        $this->detailClientId = $clientId;
    }

    public function closeDetail(): void
    {
        $this->detailClientId = null;
    }

    public function openAdjust(int $clientId): void
    {
        $this->authorizePressingAction('pressing_loyalty.adjust');
        $this->adjustClientId = $clientId;
        $this->adjust_points = '';
        $this->adjust_reason = '';
    }

    public function closeAdjust(): void
    {
        $this->adjustClientId = null;
    }

    public function saveAdjust(): void
    {
        $this->authorizePressingAction('pressing_loyalty.adjust');

        $data = $this->validate([
            'adjust_points' => ['required', 'integer', 'not_in:0'],
            'adjust_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $client = PressingClient::findOrFail($this->adjustClientId);
        app(PressingLoyaltyService::class)->adjust($client, (int) $data['adjust_points'], $data['adjust_reason'] ?: null);

        session()->flash('success', __('Points ajustés pour :client.', ['client' => $client->full_name]));
        $this->closeAdjust();
    }

    public function cancelReward(int $rewardId): void
    {
        $this->authorizePressingAction('pressing_loyalty.adjust');

        $reward = PressingLoyaltyReward::findOrFail($rewardId);
        if ($reward->status === PressingLoyaltyReward::STATUS_AVAILABLE) {
            $reward->update(['status' => PressingLoyaltyReward::STATUS_CANCELLED]);
            session()->flash('success', __('Récompense annulée.'));
        }
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_loyalty.view');

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        $like = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $clients = null;
        $rewards = null;

        if ($this->tab === 'clients') {
            $clients = PressingClient::query()
                ->with('agence')
                ->when($this->agenceFilter, fn ($q) => $q->where('agence_id', $this->agenceFilter))
                ->when($this->search !== '', function ($q) use ($like) {
                    $term = '%'.$this->search.'%';
                    $q->where(function ($inner) use ($like, $term) {
                        $inner->where('first_name', $like, $term)
                            ->orWhere('last_name', $like, $term)
                            ->orWhere('whatsapp', $like, $term)
                            ->orWhere('code', $like, $term);
                    });
                })
                ->orderByDesc('loyalty_points')
                ->orderBy('last_name')
                ->paginate(15);
        } else {
            $rewards = PressingLoyaltyReward::query()
                ->with(['client', 'order'])
                ->when($this->search !== '', function ($q) use ($like) {
                    $term = '%'.$this->search.'%';
                    $q->where('code', $like, $term)
                        ->orWhereHas('client', function ($inner) use ($like, $term) {
                            $inner->where('first_name', $like, $term)
                                ->orWhere('last_name', $like, $term);
                        });
                })
                ->orderByRaw("CASE status WHEN 'available' THEN 0 ELSE 1 END")
                ->latest('id')
                ->paginate(15);
        }

        $detailClient = null;
        if ($this->detailClientId) {
            $detailClient = PressingClient::with([
                'loyaltyEntries' => fn ($q) => $q->limit(20),
                'loyaltyRewards' => fn ($q) => $q->limit(20),
            ])->find($this->detailClientId);
        }

        $service = app(PressingLoyaltyService::class);

        return view('pressing::livewire.loyalty.index', [
            'clients' => $clients,
            'rewards' => $rewards,
            'detailClient' => $detailClient,
            'agences' => Agence::orderBy('name')->get(),
            'tenantCode' => $tenantCode,
            'active' => $service->active(),
            'threshold' => PressingSettings::loyaltyThreshold(),
            'canAdjust' => $this->can('pressing_loyalty.adjust'),
            'summary' => [
                'members' => PressingClient::where('loyalty_points', '>', 0)->count(),
                'available_rewards' => PressingLoyaltyReward::where('status', PressingLoyaltyReward::STATUS_AVAILABLE)->count(),
                'used_rewards' => PressingLoyaltyReward::where('status', PressingLoyaltyReward::STATUS_USED)->count(),
            ],
        ])->layout('layouts.app', [
            'title' => __('Fidélité'),
            'subtitle' => __('Points et récompenses clients'),
        ]);
    }
}
