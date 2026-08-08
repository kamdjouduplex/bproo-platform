<?php

namespace Pharma\Http\Livewire;

use Illuminate\Support\Facades\Route;
use Livewire\Component;

class PharmaHub extends Component
{
    public function render()
    {
        $links = array_values(array_filter([
            Route::has('tenant.sales.index') ? ['label' => 'Point de vente', 'route' => 'tenant.sales.index', 'hint' => 'Vente rapide, scanner, ordonnances'] : null,
            Route::has('tenant.batches.index') ? ['label' => 'Lots & péremption', 'route' => 'tenant.batches.index', 'hint' => 'Alertes expiration, FEFO'] : null,
            Route::has('tenant.stock.index') ? ['label' => 'Stock', 'route' => 'tenant.stock.index', 'hint' => 'Niveaux, transferts, ruptures'] : null,
            Route::has('tenant.purchases.index') ? ['label' => 'Achats', 'route' => 'tenant.purchases.index', 'hint' => 'Commandes & réceptions'] : null,
            Route::has('tenant.prescriptions.index') ? ['label' => 'Ordonnances', 'route' => 'tenant.prescriptions.index', 'hint' => 'Délivrance & historique'] : null,
            Route::has('tenant.items.index') ? ['label' => 'Médicaments', 'route' => 'tenant.items.index', 'hint' => 'Catalogue produits'] : null,
            Route::has('tenant.caisse.index') ? ['label' => 'Caisse', 'route' => 'tenant.caisse.index', 'hint' => 'Sessions & écarts'] : null,
            Route::has('tenant.clients.index') ? ['label' => 'Clients', 'route' => 'tenant.clients.index', 'hint' => 'Particuliers, cliniques, crédit'] : null,
        ]));

        return view('pharma::livewire.hub', [
            'links' => $links,
        ])->layout('layouts.app', [
            'title' => 'Pharmacie',
            'subtitle' => 'Bproo Pharma — hub métier',
        ]);
    }
}
