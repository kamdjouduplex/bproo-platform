<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h2 class="sch-detail-toolbar__title">{{ $fee->name }}</h2><p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p></div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.fees.index', ['tenant' => $tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.fees.show', ['tenant' => $tenantCode, 'id' => $fee->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.fees.manage', ['tenant' => $tenantCode, 'id' => $fee->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach([
                'Nom' => $fee->name,
                'Année' => $fee->academicYear?->name ?? 'Toutes',
                'Classe' => $fee->schoolClass?->name ?? 'Toutes',
                'Montant' => number_format((float)$fee->amount, 0, ',', ' ').' '.$fee->currency_code,
                'Statut' => $fee->is_active ? 'Actif' : 'Inactif',
                'Description' => $fee->description ?? '—',
            ] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button class="btn btn-secondary btn-sm" wire:click="{{ $fee->is_active ? 'deactivate' : 'activate' }}">{{ $fee->is_active ? 'Désactiver' : 'Activer' }}</button>
            </div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Modifier le barème</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Nom</label><input class="input" wire:model="name"></div>
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId"><option value="">Toutes</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label class="label">Montant</label><input class="input" type="number" step="0.01" wire:model="amount"></div>
                    <div><label class="label">Devise</label><input class="input" wire:model="currencyCode"></div>
                    <div class="form-span-2"><label class="label">Description</label><textarea class="input" rows="2" wire:model="description"></textarea></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Actif</label></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
