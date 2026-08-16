<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h2 class="sch-detail-toolbar__title">Inscription #{{ $enrollment->id }}</h2><p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p></div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)<a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.enrollments.show', ['tenant' => $tenantCode, 'id' => $enrollment->id]) }}">Voir</a><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else<a class="btn btn-primary btn-sm" href="{{ route('tenant.school.enrollments.manage', ['tenant' => $tenantCode, 'id' => $enrollment->id]) }}">Gérer</a>@endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach(['Élève' => ($enrollment->student?->student_code.' — '.$enrollment->student?->full_name), 'Année académique' => $enrollment->academicYear?->name ?? '—', 'Classe' => $enrollment->schoolClass?->name ?? '—', 'Section' => $sectionLabel ?? '—', 'Statut' => $statusLabel, 'Créée le' => $enrollment->created_at?->format('d/m/Y H:i') ?? '—', 'Mise à jour' => $enrollment->updated_at?->format('d/m/Y H:i') ?? '—'] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)<div class="sch-actions-panel"><div class="sch-actions-panel__label">Actions</div><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button></div>@endif
    </section>
    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel"><div class="sch-modal sch-modal--wide"><div class="sch-modal__head"><h3 class="sch-modal__title">Modifier l’inscription</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
            <div class="sch-modal__body"><div class="form-grid">
                <div class="form-span-2">@include('school::livewire.partials.searchable-student')</div>
                <div><label class="label">Année académique</label><select class="input" wire:model="academicYearId"><option value="">—</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                <div><label class="label">Classe</label><select class="input" wire:model="classId"><option value="">—</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label">Section</label><select class="input" wire:model="section"><option value="">—</option>@foreach($sections as $s)<option value="{{ $s->value }}">{{ $s->label }}</option>@endforeach</select></div>
                <div><label class="label">Statut</label><select class="input" wire:model="status">@foreach($statuses as $s)<option value="{{ $s->value }}">{{ $s->label }}</option>@endforeach</select></div>
            </div></div><div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
        </div></div>
    @endif
</div>
