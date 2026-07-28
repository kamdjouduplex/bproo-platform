<div>
    <div class="card">
        <h2 class="card-title text-base mb-4">{{ $moduleId ? __('Modifier un module') : __('Créer un module') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Clé module') }}</label>
                <input class="input" placeholder="Ex: items" wire:model="key">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Nom du module') }}</label>
                <input class="input" placeholder="Ex: Articles" wire:model="label">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Description') }}</label>
                <input class="input" placeholder="Résumé du module" wire:model="description">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Nom de route') }}</label>
                <input class="input" placeholder="Ex: tenant.items.index" wire:model="route_name">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Handler cycle de vie') }}</label>
                <input class="input" placeholder="Ex: App\Modules\ItemsModule" wire:model="lifecycle_handler">
            </div>
            <div class="field">
                <label class="flex items-center gap-2 text-xs text-slate-500 font-medium cursor-pointer mt-5">
                    <input type="checkbox" wire:model="enabled_by_default" class="accent-blue-600">
                    {{ __('Actif par défaut') }}
                </label>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-6">
            <a class="btn btn-secondary" href="{{ route('system.modules') }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save">
                {{ $moduleId ? __('Mettre à jour') : __('Créer') }}
            </button>
        </div>
    </div>
</div>
