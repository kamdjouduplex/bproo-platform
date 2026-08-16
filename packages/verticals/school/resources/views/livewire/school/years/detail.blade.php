<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $year->name }}</h2>
                <p class="sch-detail-toolbar__hint">
                    {{ $isManage ? 'Page de gestion' : 'Page de consultation' }}
                    @if($year->is_active)
                        · <span class="badge badge-success">Active</span>
                    @else
                        · <span class="badge badge-secondary">Inactive</span>
                    @endif
                </p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.years.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.years.show', ['tenant' => $tenantCode, 'id' => $year->id]) }}">Voir</a>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.years.manage', ['tenant' => $tenantCode, 'id' => $year->id]) }}">Gérer</a>
                @endif
            </div>
        </div>

        <div class="sch-info-grid">
            <div class="sch-info-item">
                <span class="sch-info-item__label">Nom</span>
                <div class="sch-info-item__value">{{ $year->name }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Code</span>
                <div class="sch-info-item__value">{{ $year->code ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Date de début</span>
                <div class="sch-info-item__value">{{ $year->start_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Date de fin</span>
                <div class="sch-info-item__value">{{ $year->end_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Statut</span>
                <div class="sch-info-item__value">{{ $year->is_active ? 'Active' : 'Inactive' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Créée le</span>
                <div class="sch-info-item__value">{{ $year->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
            <div class="sch-info-item">
                <span class="sch-info-item__label">Mise à jour</span>
                <div class="sch-info-item__value">{{ $year->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        </div>

        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button type="button" class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @if($year->is_active)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="deactivate"
                            wire:confirm="Désactiver cette année académique ?">Désactiver</button>
                @else
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="activate"
                            wire:confirm="Activer cette année ? Les autres seront désactivées.">Activer</button>
                @endif
            </div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Modifier l’année</h3>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Code (optionnel)</label>
                            <input class="input" type="text" wire:model="code">
                            @error('code') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" type="text" wire:model="name">
                            @error('name') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Début</label>
                            <input class="input" type="date" wire:model="startDate">
                        </div>
                        <div>
                            <label class="label">Fin</label>
                            <input class="input" type="date" wire:model="endDate">
                            @error('endDate') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-span-2">
                            <label class="label" style="margin:0;">
                                <input type="checkbox" wire:model="isActive"> Activer (désactive les autres)
                            </label>
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
