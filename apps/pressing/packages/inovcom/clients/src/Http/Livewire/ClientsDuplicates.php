<?php

namespace InovCom\Clients\Http\Livewire;

use InovCom\Clients\Concerns\AuthorizesClientActions;
use InovCom\Clients\Services\ClientDuplicateService;
use Livewire\Component;

class ClientsDuplicates extends Component
{
    use AuthorizesClientActions;

    /** @var array<string, int> signature => targetId */
    public array $targets = [];

    public function mergeGroup(string $signature): void
    {
        if (! $this->can('clients.update')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $targetId = (int) ($this->targets[$signature] ?? 0);
        if ($targetId <= 0) {
            session()->flash('error', 'Sélectionnez le client à conserver.');
            return;
        }

        $service = app(ClientDuplicateService::class);
        $group = collect($service->findGroups())
            ->first(fn ($g) => $g['clients']->pluck('id')->sort()->implode('-') === $signature);

        if (! $group) {
            session()->flash('error', 'Groupe introuvable (peut-être déjà fusionné).');
            return;
        }

        $ids = $group['clients']->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! in_array($targetId, $ids, true)) {
            session()->flash('error', 'Client cible invalide.');
            return;
        }

        $sources = array_values(array_filter($ids, fn ($id) => $id !== $targetId));
        $service->merge($targetId, $sources);

        unset($this->targets[$signature]);
        session()->flash('success', count($sources) . ' doublon(s) fusionné(s) dans le client conservé.');
    }

    public function render()
    {
        $service = app(ClientDuplicateService::class);
        $groups = collect($service->findGroups())->map(function ($g) {
            $g['signature'] = $g['clients']->pluck('id')->sort()->implode('-');
            return $g;
        });

        return view('inovcom-clients::livewire.clients.duplicates')
            ->layout('layouts.app', [
                'title' => 'Doublons clients',
                'subtitle' => 'Détection & fusion',
            ])
            ->with([
                'groups' => $groups,
                'canUpdate' => $this->can('clients.update'),
            ]);
    }
}
