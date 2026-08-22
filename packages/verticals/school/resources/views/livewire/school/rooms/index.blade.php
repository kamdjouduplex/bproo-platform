<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">Salles</h2>
                <p class="sch-modal__hint" style="margin:4px 0 0;">Une <strong>classe</strong> est un groupe d’élèves (ex. 6ème A). Une <strong>salle</strong> est le local où se tient le cours (ex. Salle 3).</p>
            </div>
            <div class="sch-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.courses.index', ['tenant' => $tenantCode]) }}">Cours</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.timetable.index', ['tenant' => $tenantCode]) }}">Emploi du temps</a>
                @if($canManage)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle salle</button>
                @endif
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Nom de salle…">
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Salle</th>
                        <th>Capacité</th>
                        <th>Notes</th>
                        <th>Statut</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rooms as $room)
                        <tr>
                            <td><strong>{{ $room->name }}</strong></td>
                            <td>{{ $room->capacity ?: '—' }}</td>
                            <td>{{ $room->notes ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $room->is_active ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $room->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="right">
                                @if($canManage)
                                    <div class="sch-row-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $room->id }})">Modifier</button>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $room->id }})" wire:confirm="Retirer cette salle ? Les cours garderont juste le nom, sans lien.">Retirer</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune salle. Créez-les ici, puis sélectionnez-les dans les cours et l’emploi du temps.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rooms->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">{{ $editingId ? 'Modifier la salle' : 'Nouvelle salle' }}</h3>
                        <p class="sch-modal__hint">Ex. Salle 3, Labo chimie, Info 1, Amphithéâtre.</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" type="text" wire:model="name" placeholder="Salle 3">
                            @error('name') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Capacité (optionnel)</label>
                            <input class="input" type="number" min="1" wire:model="capacity" placeholder="Ex. 45">
                            @error('capacity') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label">Notes</label>
                            <input class="input" type="text" wire:model="notes" placeholder="Étage, vidéoprojecteur…">
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
