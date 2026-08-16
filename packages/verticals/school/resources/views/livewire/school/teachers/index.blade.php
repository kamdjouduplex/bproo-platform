<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Enseignants</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvel enseignant</button>
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher nom, téléphone, email…">
            <select class="input" wire:model.live="filterActive" style="max-width:160px;">
                <option value="">Tous les statuts</option>
                <option value="1">Actifs</option>
                <option value="0">Inactifs</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Actif</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $t)
                        <tr>
                            <td><strong>{{ $t->full_name }}</strong></td>
                            <td>{{ $t->phone ?? '—' }}</td>
                            <td>{{ $t->email ?? '—' }}</td>
                            <td>
                                @if($t->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.teachers.show', ['tenant' => $tenantCode, 'id' => $t->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.teachers.manage', ['tenant' => $tenantCode, 'id' => $t->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucun enseignant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $teachers->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Nouvel enseignant</h3>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div class="form-span-2">
                            <label class="label">Nom complet</label>
                            <input class="input" type="text" wire:model="fullName">
                            @error('fullName') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Téléphone</label>
                            <input class="input" type="text" wire:model="phone">
                            @error('phone') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Email</label>
                            <input class="input" type="email" wire:model="email">
                            @error('email') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label">Adresse</label>
                            <textarea class="input" rows="2" wire:model="address"></textarea>
                            @error('address') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label" style="margin:0;">
                                <input type="checkbox" wire:model="isActive"> Actif
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
