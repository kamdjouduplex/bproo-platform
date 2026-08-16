<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    @include('school::livewire.school.publications._nav')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Publications de résultats</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle publication</button>
            </div>
        </div>
        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Titre…">
            <select class="input" wire:model.live="filterYearId" style="max-width:200px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
            </select>
            <select class="input" wire:model.live="filterStatus" style="max-width:180px;">
                <option value="">Tous statuts</option>
                <option value="draft">Brouillon</option>
                <option value="pending_approval">En approbation</option>
                <option value="published">Publié</option>
                <option value="rejected">Rejeté</option>
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th><th>Année</th><th>Classe</th><th>Règle</th><th>Éligibles</th><th>Statut</th><th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row->title }}</strong></td>
                            <td>{{ $row->academicYear?->name }}</td>
                            <td>{{ $row->schoolClass?->name ?? 'Toutes' }}</td>
                            <td>{{ $row->rule?->name ?? '—' }}</td>
                            <td>{{ $row->eligible_count }}/{{ $row->lines_count }}</td>
                            <td>
                                @if($row->status === 'published')
                                    <span class="badge badge-success">Publié</span>
                                @elseif($row->status === 'pending_approval')
                                    <span class="badge badge-secondary">Approbation</span>
                                @elseif($row->status === 'rejected')
                                    <span class="badge badge-secondary">Rejeté</span>
                                @else
                                    <span class="badge badge-secondary">Brouillon</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.publications.show', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.publications.manage', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune publication.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Nouvelle publication</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Titre</label><input class="input" wire:model="title">@error('title') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId"><option value="">—</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select>@error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div class="form-span-2"><label class="label">Règle de publication</label><select class="input" wire:model="publicationRuleId"><option value="">—</option>@foreach($rules as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
                    <div class="form-span-2"><label class="label">Notes</label><textarea class="input" rows="2" wire:model="notes"></textarea></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
