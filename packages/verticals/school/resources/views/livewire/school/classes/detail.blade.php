<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $class->name }}</h2>
                <p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.classes.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.classes.show', ['tenant' => $tenantCode, 'id' => $class->id]) }}">Voir</a>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.classes.manage', ['tenant' => $tenantCode, 'id' => $class->id]) }}">Gérer</a>
                @endif
            </div>
        </div>

        <div class="sch-info-grid">
            <div class="sch-info-item">
                <span class="sch-info-item__label">Nom</span>
                <div class="sch-info-item__value">{{ $class->name }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Section</span>
                <div class="sch-info-item__value">{{ $sectionLabel ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Statut</span>
                <div class="sch-info-item__value">{{ $class->is_active ? 'Active' : 'Inactive' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Créée le</span>
                <div class="sch-info-item__value">{{ $class->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Mise à jour</span>
                <div class="sch-info-item__value">{{ $class->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        </div>

        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @if($class->is_active)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="deactivate">Désactiver</button>
                @else
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="activate">Activer</button>
                @endif
            </div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div><h3 class="sch-modal__title">Modifier la classe</h3></div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" type="text" wire:model="name">
                            @error('name') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Section</label>
                            <select class="input" wire:model="section">
                                <option value="">—</option>
                                @foreach($sections as $s)
                                    <option value="{{ $s->value }}">{{ $s->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-span-2">
                            <label class="label" style="margin:0;"><input type="checkbox" wire:model="isActive"> Active</label>
                        </div>
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif
</div>
