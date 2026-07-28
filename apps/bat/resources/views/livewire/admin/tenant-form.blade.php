<div>
    <div class="card mb-4">
        <h2 class="card-title text-base">{{ $tenantId ? __('Modifier une entreprise') : __('Créer une entreprise') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <div class="field">
                <label class="field-label">{{ __('Nom de l\'entreprise') }}</label>
                <input class="input" placeholder="Ex: Boutique Centrale" wire:model="name">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Code entreprise') }}</label>
                <input class="input" placeholder="Ex: central" wire:model="code">
                @error('code') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Nom base de données') }}</label>
                <input class="input" placeholder="Ex: inovcom_central" wire:model="db_name">
            </div>
            <div class="field">
                <label class="field-label">{{ __('DB Host') }}</label>
                <input class="input" placeholder="127.0.0.1" wire:model="db_host">
            </div>
            <div class="field">
                <label class="field-label">{{ __('DB Port') }}</label>
                <input class="input" placeholder="5432" wire:model="db_port">
            </div>
            <div class="field">
                <label class="field-label">{{ __('DB Username') }}</label>
                <input class="input" placeholder="postgres" wire:model="db_username">
            </div>
            <div class="field">
                <label class="field-label">{{ __('DB Password') }}</label>
                <input class="input" placeholder="••••••••" wire:model="db_password">
            </div>
            <div class="field">
                <label class="flex items-center gap-2 text-xs text-slate-500 font-medium cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="accent-blue-600">
                    {{ __('Actif') }}
                </label>
            </div>
        </div>

        <h3 class="text-sm font-semibold text-slate-700 mt-6 mb-3">{{ __('Abonnement') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Plan') }}</label>
                <select class="input" wire:model="plan">
                    <option value="free">{{ __('Gratuit') }}</option>
                    <option value="starter">Starter</option>
                    <option value="pro">Pro</option>
                    <option value="enterprise">Enterprise</option>
                </select>
                @error('plan') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Expiration plan') }}</label>
                <input type="date" class="input" wire:model="plan_expires_at">
                @error('plan_expires_at') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">{{ __('Fin période d\'essai') }}</label>
                <input type="date" class="input" wire:model="trial_ends_at">
                @error('trial_ends_at') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        @if (!$tenantId)
        <h3 class="text-sm font-semibold text-slate-700 mt-6 mb-3">{{ __('Utilisateur administrateur initial') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Nom admin') }}</label>
                <input class="input" placeholder="Ex: Admin Boutique" wire:model="admin_name">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Email admin') }}</label>
                <input class="input" placeholder="admin@boutique.africa" wire:model="admin_email">
            </div>
            <div class="field">
                <label class="field-label">{{ __('Mot de passe') }}</label>
                <input class="input" type="password" wire:model="admin_password">
            </div>
        </div>
        @endif

        @if ($provisioningError)
            <div class="mt-4 flex items-center gap-2 text-red-600 text-sm bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                {{ $provisioningError }}
            </div>
        @endif

        <div wire:loading wire:target="save" class="mt-3 text-sm text-slate-500 italic">
            {{ __('Provisionnement en cours… merci de patienter.') }}
        </div>

        <div class="flex items-center gap-2 mt-6">
            <a class="btn btn-secondary" href="{{ route('system.tenants') }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                {{ $tenantId ? __('Mettre à jour') : __('Créer') }}
            </button>
        </div>
    </div>
</div>
