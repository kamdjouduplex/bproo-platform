<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Étapes du workflow</h2>
            <div class="client-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle étape</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ordre</th>
                        <th>Nom</th>
                        <th>Couleur</th>
                        <th>Durée est. (min)</th>
                        <th>Finale</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stages as $stage)
                        <tr>
                            <td>{{ $stage->sort_order }}</td>
                            <td>{{ $stage->name }}</td>
                            <td><span style="display:inline-block;width:16px;height:16px;border-radius:4px;background:{{ $stage->color }};"></span> {{ $stage->color }}</td>
                            <td>{{ $stage->estimated_minutes ?? '—' }}</td>
                            <td>{{ $stage->is_final ? 'Oui' : 'Non' }}</td>
                            <td>{{ $stage->is_active ? 'Oui' : 'Non' }}</td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $stage->id }})">Modifier</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $stage->id }})" wire:confirm="Supprimer ?">Suppr.</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($showForm)
        <div class="card" style="margin-top:16px;padding:16px;">
            <h3>{{ $editingId ? 'Modifier l’étape' : 'Nouvelle étape' }}</h3>
            <div class="form-grid" style="margin-top:12px;">
                <div class="field"><label class="field-label">Nom</label><input class="input" wire:model="name">@error('name')<div style="color:#dc2626;">{{ $message }}</div>@enderror</div>
                <div class="field"><label class="field-label">Couleur</label><input class="input" type="color" wire:model="color"></div>
                <div class="field"><label class="field-label">Ordre</label><input class="input" type="number" wire:model="sort_order"></div>
                <div class="field"><label class="field-label">Durée estimée (min)</label><input class="input" type="number" wire:model="estimated_minutes"></div>
                <div class="field"><label class="field-label"><input type="checkbox" wire:model="is_final"> Étape finale</label></div>
                <div class="field"><label class="field-label"><input type="checkbox" wire:model="is_active"> Active</label></div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;">
                <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
            </div>
        </div>
    @endif
</div>
