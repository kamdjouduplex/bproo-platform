<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    @include('school::livewire.school.grading._nav')

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div>
                <h2 class="sch-detail-toolbar__title">{{ $system->name }}</h2>
                <p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }} · base /{{ rtrim(rtrim(number_format((float)$system->scale_base,2,',',' '),'0'),',') }}</p>
            </div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.systems.index', ['tenant' => $tenantCode]) }}">Retour</a>
                @if($isManage)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.systems.show', ['tenant' => $tenantCode, 'id' => $system->id]) }}">Voir</a>
                    <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.grading.systems.manage', ['tenant' => $tenantCode, 'id' => $system->id]) }}">Gérer</a>
                @endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach(['Nom'=>$system->name,'Code'=>$system->code??'—','Base'=>$system->scale_base,'Description'=>$system->description??'—','Actif'=>$system->is_active?'Oui':'Non'] as $label=>$value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
        </div>
        @if($isManage)
            <div class="sch-actions-panel">
                <div class="sch-actions-panel__label">Actions</div>
                <button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                <button class="btn btn-secondary btn-sm" wire:click="{{ $system->is_active ? 'deactivate' : 'activate' }}">{{ $system->is_active ? 'Désactiver' : 'Activer' }}</button>
                <button class="btn btn-secondary btn-sm" wire:click="createScale">Nouvelle tranche</button>
            </div>
        @endif
    </section>

    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h3 class="sch-detail-toolbar__title" style="font-size:1rem;">Barème (notes /{{ rtrim(rtrim(number_format((float) $system->scale_base, 2, ',', ' '), '0'), ',') }})</h3></div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Libellé</th><th>Min</th><th>Max</th><th>Réussite</th><th>Ordre</th>@if($isManage)<th class="right">Actions</th>@endif</tr></thead>
                <tbody>
                    @forelse($system->scales as $scale)
                        <tr>
                            <td><strong>{{ $scale->label }}</strong></td>
                            <td>{{ rtrim(rtrim(number_format((float) $scale->min_percent, 2, ',', ' '), '0'), ',') }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $scale->max_percent, 2, ',', ' '), '0'), ',') }}</td>
                            <td>{{ $scale->is_pass ? 'Oui' : 'Non' }}</td>
                            <td>{{ $scale->sort_order }}</td>
                            @if($isManage)
                                <td class="right">
                                    <div class="sch-row-actions">
                                        <button class="btn btn-secondary btn-sm" wire:click="editScale({{ $scale->id }})">Modifier</button>
                                        <button class="btn btn-secondary btn-sm" wire:click="deleteScale({{ $scale->id }})" wire:confirm="Supprimer cette tranche ?">Suppr.</button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isManage ? 6 : 5 }}">Aucune tranche. Ajoutez un barème (ex. Excellent 16–20).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Modifier le système</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div><label class="label">Nom</label><input class="input" wire:model="name"></div>
                    <div><label class="label">Code</label><input class="input" wire:model="code"></div>
                    <div><label class="label">Base</label><input class="input" type="number" step="0.01" wire:model="scaleBase"></div>
                    <div class="form-span-2"><label class="label">Description</label><textarea class="input" rows="2" wire:model="description"></textarea></div>
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Actif</label></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif

    @if($showScaleForm)
        <div class="sch-modal-backdrop" wire:click.self="cancelScale">
            <div class="sch-modal">
                <div class="sch-modal__head"><h3 class="sch-modal__title">{{ $editingScaleId ? 'Modifier la tranche' : 'Nouvelle tranche' }}</h3><button class="sch-modal__close" wire:click="cancelScale">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label">Libellé</label><input class="input" wire:model="scaleLabel">@error('scaleLabel') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Min /{{ rtrim(rtrim(number_format((float) $system->scale_base, 2, ',', ' '), '0'), ',') }}</label><input class="input" type="number" step="0.01" min="0" max="{{ $system->scale_base }}" wire:model="scaleMin">@error('scaleMin') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Max /{{ rtrim(rtrim(number_format((float) $system->scale_base, 2, ',', ' '), '0'), ',') }}</label><input class="input" type="number" step="0.01" min="0" max="{{ $system->scale_base }}" wire:model="scaleMax">@error('scaleMax') <span class="text-error">{{ $message }}</span> @enderror</div>
                    <div><label class="label">Ordre</label><input class="input" type="number" wire:model="scaleSort"></div>
                    <div><label class="label"><input type="checkbox" wire:model="scaleIsPass"> Compte comme réussite</label></div>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancelScale">Annuler</button><button class="btn btn-primary" wire:click="saveScale">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
