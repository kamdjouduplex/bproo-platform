<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    @include('school::livewire.school.publications._nav')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Règles de publication</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle règle</button>
            </div>
        </div>
        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Nom…">
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th><th>Périmètre</th><th>Frais</th><th>Notes validées</th><th>Directeur</th><th>Actif</th><th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row->name }}</strong></td>
                            <td>{{ $row->academicYear?->name ?? 'Toutes années' }}{{ $row->schoolClass ? ' · '.$row->schoolClass->name : '' }}</td>
                            <td>{{ $row->require_fees_paid ? ('Oui'.($row->min_fees_amount ? ' ≥ '.$row->min_fees_amount : '')) : 'Non' }}</td>
                            <td>{{ $row->require_validated_marks ? 'Oui' : 'Non' }}</td>
                            <td>{{ $row->require_director_approval ? 'Oui' : 'Non' }}</td>
                            <td>@if($row->is_active)<span class="badge badge-success">Oui</span>@else<span class="badge badge-secondary">Non</span>@endif</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.publications.rules.show', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.publications.rules.manage', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune règle.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Nouvelle règle</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Nom</label><input class="input" wire:model="name">@error('name') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Année (optionnel)</label><select class="input" wire:model="academicYearId"><option value="">Toutes</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe (optionnel)</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model.live="requireFeesPaid"> Exiger frais payés</label></div>
                    @if($requireFeesPaid)
                        <div class="form-span-2"><label class="label">Montant minimum</label><input class="input" type="number" step="0.01" wire:model="minFeesAmount"></div>
                    @endif
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="requireValidatedMarks"> Exiger notes validées</label></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="requireDirectorApproval"> Exiger approbation directeur</label></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Active</label></div>
                    <div class="form-span-2"><label class="label">Description</label><textarea class="input" rows="2" wire:model="description"></textarea></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
