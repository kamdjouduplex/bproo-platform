@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Agences</h2>
            <div class="client-list-head__actions">
                @if ($canCreate)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle agence</button>
                @endif
            </div>
        </div>

        <div style="padding:12px 16px;">
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher code, nom, ville…">
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Ville</th>
                        <th>Téléphone</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agences as $agence)
                        <tr>
                            <td>{{ $agence->code }}</td>
                            <td>{{ $agence->name }}</td>
                            <td>{{ $agence->city }}</td>
                            <td>{{ $agence->phone }}</td>
                            <td>{{ $agence->manager?->name ?? '—' }}</td>
                            <td>{{ $agence->is_active ? 'Active' : 'Inactive' }}</td>
                            <td style="white-space:nowrap;">
                                @if ($canUpdate)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $agence->id }})">Modifier</button>
                                @endif
                                @if ($canDelete)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $agence->id }})" wire:confirm="Supprimer cette agence ?">Suppr.</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune agence.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding:12px 16px;">{{ $agences->links() }}</div>
    </section>

    @if ($showForm)
        <div class="card" style="margin-top:16px;padding:16px;">
            <h3>{{ $editingId ? 'Modifier l’agence' : 'Nouvelle agence' }}</h3>
            <div class="form-grid" style="margin-top:12px;">
                <div class="field"><label class="field-label">Code</label><input class="input" wire:model="code">@error('code')<div style="color:#dc2626;">{{ $message }}</div>@enderror</div>
                <div class="field"><label class="field-label">Nom</label><input class="input" wire:model="name">@error('name')<div style="color:#dc2626;">{{ $message }}</div>@enderror</div>
                <div class="field"><label class="field-label">Pays</label><input class="input" wire:model="country"></div>
                <div class="field"><label class="field-label">Ville</label><input class="input" wire:model="city"></div>
                <div class="field"><label class="field-label">Localisation</label><input class="input" wire:model="location"></div>
                <div class="field"><label class="field-label">Téléphone</label><input class="input" wire:model="phone"></div>
                <div class="field"><label class="field-label">Email</label><input class="input" wire:model="email"></div>
                <div class="field">
                    <label class="field-label">Responsable</label>
                    <select class="input" wire:model="manager_user_id">
                        <option value="">—</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label"><input type="checkbox" wire:model="is_active"> Active</label>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;">
                <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
            </div>
        </div>
    @endif
</div>
