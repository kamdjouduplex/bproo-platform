@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>
    <div class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">
            {{ $userId ? __('Modifier utilisateur') : __('Nouvel utilisateur') }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Nom') }}</label>
                <input class="input" wire:model="name" placeholder="{{ __('Ex: Jean Dupont') }}">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Email') }}</label>
                <input class="input" wire:model="email" type="email" placeholder="jean@example.com">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">
                    {{ __('Mot de passe') }}
                    @if($userId) <span class="text-slate-400 font-normal">({{ __('vide = ne pas modifier') }})</span> @endif
                </label>
                <input class="input" wire:model="password" type="password" placeholder="{{ __('Min. 8 caractères') }}">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Confirmer mot de passe') }}</label>
                <input class="input" wire:model="password_confirmation" type="password">
                @error('password_confirmation') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Rôles') }}</label>
                <div class="flex flex-wrap gap-2 mt-1">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-[13px] text-slate-700 select-none">
                            <input type="checkbox" wire:model="role_ids" value="{{ $role->id }}" class="rounded border-slate-300 text-blue-600">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('role_ids.*') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field sm:col-span-2">
                <label class="flex items-center gap-2 cursor-pointer text-[13px] text-slate-700 select-none">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-blue-600">
                    {{ __('Actif') }}
                </label>
            </div>
        </div>

        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">{{ __('Retour') }}</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $userId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
    </div>
</div>
