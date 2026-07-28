<x-app-layout :title="__('Administration')" :subtitle="__('Vue d\'ensemble de la plateforme')">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="card">
            <div class="card-title">{{ __('Entreprises') }}</div>
            <div class="card-body">{{ __('Créer et gérer les entreprises (tenants), leurs bases et statuts.') }}</div>
        </div>
        <div class="card">
            <div class="card-title">{{ __('Modules') }}</div>
            <div class="card-body">{{ __('Activer ou désactiver les fonctionnalités par entreprise.') }}</div>
        </div>
        <div class="card">
            <div class="card-title">{{ __('Paramètres globaux') }}</div>
            <div class="card-body">{{ __('Paramètres régionaux, taxes, devises et règles.') }}</div>
        </div>
    </div>
</x-app-layout>
