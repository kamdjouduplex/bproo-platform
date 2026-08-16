<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h2 class="sch-detail-toolbar__title">{{ $teacher->full_name }}</h2><p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p></div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.teachers.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)<a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.teachers.show', ['tenant' => $tenantCode, 'id' => $teacher->id]) }}">Voir</a><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else<a class="btn btn-primary btn-sm" href="{{ route('tenant.school.teachers.manage', ['tenant' => $tenantCode, 'id' => $teacher->id]) }}">Gérer</a>@endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach(['Nom complet' => $teacher->full_name, 'Téléphone' => $teacher->phone ?? '—', 'Email' => $teacher->email ?? '—', 'Adresse' => $teacher->address ?? '—', 'Statut' => $teacher->is_active ? 'Actif' : 'Inactif', 'Créé le' => $teacher->created_at?->format('d/m/Y H:i') ?? '—', 'Mise à jour' => $teacher->updated_at?->format('d/m/Y H:i') ?? '—'] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)<div class="sch-actions-panel"><div class="sch-actions-panel__label">Actions</div><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button><button class="btn btn-secondary btn-sm" wire:click="{{ $teacher->is_active ? 'deactivate' : 'activate' }}">{{ $teacher->is_active ? 'Désactiver' : 'Activer' }}</button></div>@endif
    </section>
    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel"><div class="sch-modal"><div class="sch-modal__head"><h3 class="sch-modal__title">Modifier l’enseignant</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
            <div class="sch-modal__body"><div class="form-grid">
                <div class="form-span-2"><label class="label">Nom complet</label><input class="input" wire:model="fullName">@error('fullName') <span class="text-error">{{ $message }}</span> @enderror</div>
                <div><label class="label">Téléphone</label><input class="input" wire:model="phone"></div><div><label class="label">Email</label><input class="input" type="email" wire:model="email">@error('email') <span class="text-error">{{ $message }}</span> @enderror</div>
                <div class="form-span-2"><label class="label">Adresse</label><textarea class="input" wire:model="address"></textarea></div><div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Actif</label></div>
            </div></div><div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
        </div></div>
    @endif
</div>
