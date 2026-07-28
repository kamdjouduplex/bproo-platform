<x-app-layout :title="__('Espace entreprise')" :subtitle="__('Tableau de bord')">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="card">
            <div class="card-title">{{ __('Clients') }}</div>
            <div class="card-body">{{ __('Centre du système : fiches clients, contacts, historique.') }}</div>
        </div>
        <div class="card">
            <div class="card-title">{{ __('Articles') }}</div>
            <div class="card-body">{{ __('Catalogue produits/services, unités et tarifs.') }}</div>
        </div>
    </div>
</x-app-layout>
