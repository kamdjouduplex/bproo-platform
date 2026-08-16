<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    @include('school::livewire.school.publications._nav')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $rule->name }}</h2>
                <p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.publications.rules.index', ['tenant'=>$tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.publications.rules.show', ['tenant'=>$tenantCode,'id'=>$rule->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.publications.rules.manage', ['tenant'=>$tenantCode,'id'=>$rule->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            <div class="sch-info-item"><span class="sch-info-item__label">Année</span><div class="sch-info-item__value">{{ $rule->academicYear?->name ?? 'Toutes' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Classe</span><div class="sch-info-item__value">{{ $rule->schoolClass?->name ?? 'Toutes' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Frais payés</span><div class="sch-info-item__value">{{ $rule->require_fees_paid ? 'Oui'.($rule->min_fees_amount ? ' ≥ '.$rule->min_fees_amount : '') : 'Non' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Notes validées</span><div class="sch-info-item__value">{{ $rule->require_validated_marks ? 'Oui' : 'Non' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Approbation directeur</span><div class="sch-info-item__value">{{ $rule->require_director_approval ? 'Oui' : 'Non' }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Active</span><div class="sch-info-item__value">{{ $rule->is_active ? 'Oui' : 'Non' }}</div></div>
            <div class="sch-info-item sch-info-item--wide"><span class="sch-info-item__label">Description</span><div class="sch-info-item__value">{{ $rule->description ?? '—' }}</div></div>
        </div>
        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button class="btn btn-secondary btn-sm" wire:click="{{ $rule->is_active ? 'deactivate' : 'activate' }}">{{ $rule->is_active ? 'Désactiver' : 'Activer' }}</button>
            </div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Modifier la règle</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Nom</label><input class="input" wire:model="name"></div>
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId"><option value="">Toutes</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">Toutes</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
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
