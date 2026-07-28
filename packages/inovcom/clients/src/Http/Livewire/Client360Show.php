<?php

namespace InovCom\Clients\Http\Livewire;

use InovCom\Clients\Concerns\AuthorizesClientActions;
use InovCom\Clients\Concerns\ManagesClientWorkspace;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Models\ClientDocument;
use InovCom\Clients\Models\ClientNote;
use InovCom\Clients\Services\ClientAgingService;
use InovCom\Clients\Services\ClientDebtInsightService;
use Livewire\Component;
use Livewire\WithFileUploads;

class Client360Show extends Component
{
    use AuthorizesClientActions;
    use ManagesClientWorkspace;
    use WithFileUploads;

    public function mount(Client $client): void
    {
        $this->mountClientWorkspace($client);
    }

    public function render()
    {
        $debtService = app(ClientDebtInsightService::class);
        $agingService = app(ClientAgingService::class);

        return view('inovcom-clients::livewire.clients.show-360')
            ->layout('layouts.app', [
                'title' => 'Vue 360° — ' . $this->client->code,
                'subtitle' => $this->client->name,
            ])
            ->with([
                'debtSummary' => $debtService->forClient($this->client->id),
                'debtsModule' => $debtService->moduleAvailable(),
                'stats' => $this->buildStats(),
                'paymentTerm' => $this->paymentTerm(),
                'salesrep' => $this->salesrep(),
                'history' => $this->buildHistory(),
                'aging' => $agingService->forClient($this->client->id),
                'agingLabels' => $agingService->labels(),
                'agingAvailable' => $agingService->available(),
                'canUpdate' => $this->can('clients.update'),
                'notes' => $this->client->clientNotes()->limit(30)->get(),
                'documents' => $this->client->documents()->get(),
                'reminders' => $this->client->reminders()->limit(20)->get(),
                'noteTypes' => ClientNote::TYPES,
                'documentTypes' => ClientDocument::TYPES,
                'tenantCode' => $this->tenantCode(),
            ]);
    }
}
