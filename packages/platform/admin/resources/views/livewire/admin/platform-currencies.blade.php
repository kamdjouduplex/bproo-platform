<div class="page-body">
    <section class="card" style="margin-bottom: 20px;">
        <h2 class="card-title">{{ $editingCode ? 'Modifier '.$editingCode : 'Ajouter une devise' }}</h2>
        <p style="margin-bottom: 12px; color: #64748b; font-size: 13px;">
            Ces devises alimentent la Configuration de chaque entreprise (pharmacie, ERP, pressing…). Les admins tenant choisissent ensuite lesquelles activer.
        </p>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Code ISO</label>
                <input class="input" wire:model="code" maxlength="3" placeholder="CDF" @disabled($editingCode)>
                @error('code') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Nom</label>
                <input class="input" wire:model="name" placeholder="Franc congolais">
                @error('name') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label class="field-label">Symbole</label>
                <input class="input" wire:model="symbol" placeholder="FC">
            </div>
            <div class="field">
                <label class="field-label">Décimales</label>
                <input class="input" type="number" min="0" max="6" wire:model="decimals">
            </div>
            <div class="field">
                <label class="field-label">Ordre</label>
                <input class="input" type="number" min="0" wire:model="sort_order">
            </div>
            <label class="field-toggle">
                <input type="checkbox" wire:model="is_active"> Active
            </label>
        </div>
        <div class="page-actions" style="margin-top: 12px;">
            @if ($editingCode)
                <button type="button" class="btn btn-secondary" wire:click="resetForm">Annuler</button>
            @endif
            <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Catalogue</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Symbole</th>
                        <th>Décimales</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($currencies as $cur)
                        <tr>
                            <td><strong>{{ $cur->code }}</strong></td>
                            <td>{{ $cur->name }}</td>
                            <td>{{ $cur->symbol ?: '—' }}</td>
                            <td>{{ $cur->decimals }}</td>
                            <td>{{ $cur->sort_order }}</td>
                            <td>
                                @if ($cur->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td style="display:flex; gap:6px;">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="edit('{{ $cur->code }}')">Modifier</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="toggleActive('{{ $cur->code }}')">
                                    {{ $cur->is_active ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Aucune devise. Lancez la migration Control Center puis rechargez.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
