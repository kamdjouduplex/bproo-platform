<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h2 class="sch-detail-toolbar__title">{{ $subject->name }}</h2><p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p></div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.subjects.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.subjects.show', ['tenant' => $tenantCode, 'id' => $subject->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.subjects.manage', ['tenant' => $tenantCode, 'id' => $subject->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach(['Nom' => $subject->name, 'Code' => $subject->code ?? '—', 'Statut' => $subject->is_active ? 'Active' : 'Inactive', 'Créée le' => $subject->created_at?->format('d/m/Y H:i') ?? '—', 'Mise à jour' => $subject->updated_at?->format('d/m/Y H:i') ?? '—'] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)
            <div class="sch-actions-panel"><div class="sch-actions-panel__label">Actions</div><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button class="btn btn-secondary btn-sm" wire:click="{{ $subject->is_active ? 'deactivate' : 'activate' }}">{{ $subject->is_active ? 'Désactiver' : 'Activer' }}</button>
            </div>
        @endif
    </section>
    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel"><div class="sch-modal"><div class="sch-modal__head"><h3 class="sch-modal__title">Modifier la matière</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
            <div class="sch-modal__body"><div class="form-grid"><div><label class="label">Nom</label><input class="input" wire:model="name">@error('name') <span class="text-error">{{ $message }}</span> @enderror</div><div><label class="label">Code</label><input class="input" wire:model="code"></div><div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Active</label></div></div></div>
            <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
        </div></div>
    @endif
</div>
