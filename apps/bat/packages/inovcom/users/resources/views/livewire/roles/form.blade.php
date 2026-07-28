@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
    <div class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">
            {{ $roleId ? __('Modifier le rôle') : __('Nouveau rôle') }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Nom') }}</label>
                <input class="input" wire:model="name" placeholder="{{ __('Ex: caissier') }}">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Description') }}</label>
                <textarea class="input" wire:model="description" rows="2" placeholder="{{ __('Ex: Accès caisse uniquement') }}"></textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.roles.index', ['tenant' => $tenantCode]) }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $roleId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
    </div>
</div>
