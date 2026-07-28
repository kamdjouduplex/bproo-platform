<?php

namespace InovCom\Clients\Http\Livewire;

use InovCom\Clients\Concerns\ManagesClientWorkspace;
use InovCom\Clients\Concerns\AuthorizesClientActions;
use InovCom\Clients\Models\Client;
use Livewire\Component;

class ClientShow extends Component
{
    use AuthorizesClientActions;
    use ManagesClientWorkspace;

    public function mount(Client $client): void
    {
        $this->mountClientWorkspace($client);
    }

    public function render()
    {
        $primaryContact = $this->client->contacts->firstWhere('is_primary', true)
            ?? $this->client->contacts->first();

        return view('inovcom-clients::livewire.clients.show')
            ->layout('layouts.app', [
                'title' => 'Client ' . $this->client->code,
                'subtitle' => $this->client->name,
            ])
            ->with([
                'stats' => $this->buildStats(),
                'paymentTerm' => $this->paymentTerm(),
                'salesrep' => $this->salesrep(),
                'canUpdate' => $this->can('clients.update'),
                'primaryContact' => $primaryContact,
                'tenantCode' => $this->tenantCode(),
            ]);
    }
}
