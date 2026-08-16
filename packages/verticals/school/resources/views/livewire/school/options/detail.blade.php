<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h2 class="sch-detail-toolbar__title">{{ $option->label }}</h2><p class="sch-detail-toolbar__hint">{{ $groupLabel }} · {{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p></div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.options.index', ['tenant' => $tenantCode, 'group' => $option->group_key]) }}">Retour à la liste</a>
                @if($isManage)<a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.options.show', ['tenant' => $tenantCode, 'id' => $option->id]) }}">Voir</a><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else<a class="btn btn-primary btn-sm" href="{{ route('tenant.school.options.manage', ['tenant' => $tenantCode, 'id' => $option->id]) }}">Gérer</a>@endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach(['Groupe' => $groupLabel, 'Valeur' => $option->value, 'Libellé' => $option->label, 'Ordre' => $option->sort_order, 'Statut' => $option->is_active ? 'Active' : 'Inactive', 'Créée le' => $option->created_at?->format('d/m/Y H:i') ?? '—', 'Mise à jour' => $option->updated_at?->format('d/m/Y H:i') ?? '—'] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)<div class="sch-actions-panel"><div class="sch-actions-panel__label">Actions</div><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button><button class="btn btn-secondary btn-sm" wire:click="toggleActive">{{ $option->is_active ? 'Désactiver' : 'Activer' }}</button></div>@endif
    </section>
    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel"><div class="sch-modal"><div class="sch-modal__head"><div><h3 class="sch-modal__title">Modifier l’option</h3><p class="sch-modal__hint">Groupe : {{ $groups[$activeGroup]['label'] ?? $activeGroup }}</p></div><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
            <div class="sch-modal__body"><div class="form-grid">
                <div><label class="label">Valeur</label><input class="input" wire:model="value">@error('value') <span class="text-error">{{ $message }}</span> @enderror</div>
                <div><label class="label">Libellé</label><input class="input" wire:model="label"></div><div><label class="label">Ordre</label><input class="input" type="number" wire:model="sortOrder"></div>
                <div><label class="label"><input type="checkbox" wire:model="isActive"> Active</label></div>
            </div></div><div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
        </div></div>
    @endif
</div>
