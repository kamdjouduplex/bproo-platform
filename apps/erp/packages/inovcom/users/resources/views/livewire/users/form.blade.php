@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div class="page-body">
    <section class="card">
        <h2 class="card-title">{{ $userId ? 'Modifier utilisateur' : 'Nouvel utilisateur' }}</h2>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Nom</label>
                <input class="input" wire:model="name" placeholder="Ex: Jean Dupont">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Email</label>
                <input class="input" wire:model="email" type="email" placeholder="jean@example.com">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Mot de passe {{ $userId ? '(vide = ne pas modifier)' : '' }}</label>
                <input class="input" wire:model="password" type="password" placeholder="Min. 8 caracteres">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Confirmer mot de passe</label>
                <input class="input" wire:model="password_confirmation" type="password">
                @error('password_confirmation') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Roles</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @foreach ($roles as $role)
                        <label class="field-toggle">
                            <input type="checkbox" wire:model="role_ids" value="{{ $role->id }}">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('role_ids.*') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            @if(isset($stores) && count($stores) > 0)
                <div class="field">
                    <label class="field-label">Boutique assignée</label>
                    <select class="input" wire:model="assigned_store_id">
                        <option value="">Choisir une boutique</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_store_id') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @endif
            <label class="field-toggle">
                <input type="checkbox" wire:model="is_active"> Actif
            </label>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('tenant.users.index', ['tenant' => $tenantCode]) }}">Retour</a>
            <button class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $userId ? 'Mettre a jour' : 'Enregistrer' }}</span>
                <span wire:loading wire:target="save">Enregistrement...</span>
            </button>
        </div>
    </section>
</div>
