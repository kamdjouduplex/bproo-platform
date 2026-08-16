<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Paramétrage école</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="seedDefaults">Valeurs par défaut</button>
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle option</button>
            </div>
        </div>

        <div class="sch-filters" style="padding-top: 8px;">
            <div style="display:flex; gap:6px; flex-wrap:wrap; width:100%; margin-bottom:8px;">
                @foreach($groups as $key => $label)
                    <button type="button"
                            class="btn btn-sm {{ $activeGroup === $key ? 'btn-primary' : 'btn-secondary' }}"
                            wire:click="setGroup('{{ $key }}')">
                        {{ $label['label'] }}
                    </button>
                @endforeach
            </div>
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher valeur ou libellé…">
            <select class="input" wire:model.live="filterActive" style="max-width:160px;">
                <option value="">Tous les statuts</option>
                <option value="1">Actives</option>
                <option value="0">Inactives</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Ordre</th>
                        <th>Valeur</th>
                        <th>Libellé</th>
                        <th>Active</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($options as $opt)
                        <tr>
                            <td>{{ $opt->sort_order }}</td>
                            <td><code>{{ $opt->value }}</code></td>
                            <td><strong>{{ $opt->label }}</strong></td>
                            <td>
                                @if($opt->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.options.show', ['tenant' => $tenantCode, 'id' => $opt->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.options.manage', ['tenant' => $tenantCode, 'id' => $opt->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune option dans ce groupe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $options->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Nouvelle option</h3>
                        <p class="sch-modal__hint">Groupe : {{ $groups[$activeGroup]['label'] ?? $activeGroup }}</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Valeur (clé)</label>
                            <input class="input" type="text" wire:model="value" placeholder="ex: male">
                            @error('value') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Libellé</label>
                            <input class="input" type="text" wire:model="label" placeholder="ex: Masculin">
                            @error('label') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Ordre</label>
                            <input class="input" type="number" wire:model="sortOrder" min="0">
                            @error('sortOrder') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div style="display:flex; align-items:flex-end;">
                            <label class="label" style="margin:0;">
                                <input type="checkbox" wire:model="isActive"> Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif

</div>
