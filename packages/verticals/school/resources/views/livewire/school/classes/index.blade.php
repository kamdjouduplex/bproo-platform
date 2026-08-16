<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Classes</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle classe</button>
            </div>
        </div>
        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher nom ou section…">
            <select class="input" wire:model.live="filterSection" style="max-width:180px;">
                <option value="">Toutes sections</option>
                @foreach($sections as $s)
                    <option value="{{ $s->value }}">{{ $s->label }}</option>
                @endforeach
            </select>
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
                        <th>Nom</th><th>Section</th><th>Active</th><th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $c)
                        <tr>
                            <td><strong>{{ $c->name }}</strong></td>
                            <td>{{ $c->section ?? '—' }}</td>
                            <td>@if($c->is_active)<span class="badge badge-success">Oui</span>@else<span class="badge badge-secondary">Non</span>@endif</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.classes.show', ['tenant' => $tenantCode, 'id' => $c->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.classes.manage', ['tenant' => $tenantCode, 'id' => $c->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucune classe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $classes->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div><h3 class="sch-modal__title">Nouvelle classe</h3></div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" type="text" wire:model="name">
                            @error('name') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Section</label>
                            <select class="input" wire:model="section">
                                <option value="">—</option>
                                @foreach($sections as $s)
                                    <option value="{{ $s->value }}">{{ $s->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-span-2">
                            <label class="label" style="margin:0;"><input type="checkbox" wire:model="isActive"> Active</label>
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
