<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    @include('school::livewire.school.grading._nav')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $row->subject?->name }}</h2>
                <p class="sch-detail-toolbar__hint">{{ $row->academicYear?->name }} · {{ $row->schoolClass?->name }}</p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.coefficients.index', ['tenant'=>$tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.coefficients.show', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.grading.coefficients.manage', ['tenant'=>$tenantCode,'id'=>$row->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            <div class="sch-info-item"><span class="sch-info-item__label">Année</span><div class="sch-info-item__value">{{ $row->academicYear?->name }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Classe</span><div class="sch-info-item__value">{{ $row->schoolClass?->name }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Matière</span><div class="sch-info-item__value">{{ $row->subject?->name }}</div></div>
            <div class="sch-info-item"><span class="sch-info-item__label">Coefficient</span><div class="sch-info-item__value">{{ $row->coefficient }}</div></div>
        </div>
        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button class="btn btn-secondary btn-sm" wire:click="delete" wire:confirm="Supprimer ce coefficient ?">Supprimer</button>
            </div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Modifier le coefficient</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div><label class="label">Année</label><select class="input" wire:model="academicYearId">@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                    <div><label class="label">Classe</label><select class="input" wire:model="classId">@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label class="label">Matière</label><select class="input" wire:model="subjectId">@foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                    <div><label class="label">Coefficient</label><input class="input" type="number" step="0.01" wire:model="coefficient"></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
