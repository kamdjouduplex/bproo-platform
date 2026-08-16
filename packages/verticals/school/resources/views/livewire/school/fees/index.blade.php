<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Structures de frais</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouveau barème</button>
            </div>
        </div>
        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Nom…">
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Nom</th><th>Année</th><th>Classe</th><th class="right">Montant</th><th>Statut</th><th class="right">Actions</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><strong>{{ $row->name }}</strong></td>
                        <td>{{ $row->academicYear?->name ?? 'Toutes' }}</td>
                        <td>{{ $row->schoolClass?->name ?? 'Toutes' }}</td>
                        <td class="right">{{ number_format((float)$row->amount, 0, ',', ' ') }} {{ $row->currency_code }}</td>
                        <td>{{ $row->is_active ? 'Actif' : 'Inactif' }}</td>
                        <td class="right">
                            <div class="sch-row-actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.fees.show', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Voir</a>
                                <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.fees.manage', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Gérer</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucun barème.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Nouveau barème</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Nom</label><input class="input" wire:model="name">@error('name') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId"><option value="">Toutes</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label class="label">Montant</label><input class="input" type="number" step="0.01" wire:model="amount">@error('amount') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Devise</label><input class="input" wire:model="currencyCode"></div>
                    <div class="form-span-2"><label class="label">Description</label><textarea class="input" rows="2" wire:model="description"></textarea></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Actif</label></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
