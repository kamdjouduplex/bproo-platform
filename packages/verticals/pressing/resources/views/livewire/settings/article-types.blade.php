<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Types de vêtements</h2>
            <div class="client-list-head__actions">
                @if ($canManage ?? true)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouveau type</button>
                @endif
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ordre</th>
                        <th>Nom</th>
                        <th>Code</th>
                        <th>Tarif défaut</th>
                        <th>Actif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td>{{ $type->sort_order }}</td>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->code }}</td>
                            <td>{{ number_format((float) ($type->prices->first()?->amount ?? 0), 0, ',', ' ') }}</td>
                            <td>{{ $type->is_active ? 'Oui' : 'Non' }}</td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $type->id }})">Modifier</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $type->id }})" wire:confirm="Supprimer ?">Suppr.</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun type.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showForm)
        <div class="card" style="margin-top:16px;padding:16px;">
            <h3>{{ $editingId ? 'Modifier le type' : 'Nouveau type' }}</h3>
            <div class="form-grid" style="margin-top:12px;">
                <div class="field"><label class="field-label">Nom</label><input class="input" wire:model="name">@error('name')<div style="color:#dc2626;">{{ $message }}</div>@enderror</div>
                <div class="field"><label class="field-label">Code</label><input class="input" wire:model="code"></div>
                <div class="field"><label class="field-label">Ordre</label><input class="input" type="number" wire:model="sort_order"></div>
                <div class="field"><label class="field-label">Tarif défaut</label><input class="input" type="number" step="0.01" wire:model="default_price"></div>
                <div class="field"><label class="field-label"><input type="checkbox" wire:model="is_active"> Actif</label></div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;">
                <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
            </div>
        </div>
    @endif
</div>
