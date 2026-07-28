<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Models\CustomerCredit;
use InovCom\Returns\Services\CustomerCreditService;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerCreditLedger extends Component
{
    use WithPagination;
    use AuthorizesReturnActions;

    public ?int $client = null;
    public int $perPage = 25;

    public function mount(?int $client = null): void
    {
        $this->client = $client ?: ((int) request()->query('client') ?: null);
    }

    public function render()
    {
        $service = app(CustomerCreditService::class);

        $entries = CustomerCredit::query()
            ->with('client')
            ->when($this->client, fn ($q) => $q->where('client_id', $this->client))
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $balance = $this->client ? $service->balance($this->client) : null;

        return view('inovcom-returns::livewire.customer-credits.index')
            ->layout('layouts.app', [
                'title' => 'Crédits clients',
                'subtitle' => 'Portefeuille de crédit client',
            ])
            ->with([
                'entries' => $entries,
                'balance' => $balance,
                'clients' => $this->clientOptions(),
            ]);
    }

    private function clientOptions()
    {
        if (! class_exists(\InovCom\Clients\Models\Client::class)) {
            return collect();
        }

        return \InovCom\Clients\Models\Client::query()->orderBy('name')->get(['id', 'name']);
    }
}
