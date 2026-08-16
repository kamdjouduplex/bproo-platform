<div class="page-body">
    @include('school::livewire.partials.crud-styles')
    @include('school::livewire.school.grading._nav')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Systèmes de notation</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouveau système</button>
            </div>
        </div>
        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Nom ou code…">
            <select class="input" wire:model.live="filterActive" style="max-width:160px;">
                <option value="">Tous</option>
                <option value="1">Actifs</option>
                <option value="0">Inactifs</option>
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th><th>Code</th><th>Base</th><th>Tranches</th><th>Actif</th><th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($systems as $s)
                        <tr>
                            <td><strong>{{ $s->name }}</strong></td>
                            <td>{{ $s->code ?? '—' }}</td>
                            <td>/{{ rtrim(rtrim(number_format((float)$s->scale_base, 2, ',', ' '), '0'), ',') }}</td>
                            <td>{{ $s->scales_count }}</td>
                            <td>@if($s->is_active)<span class="badge badge-success">Oui</span>@else<span class="badge badge-secondary">Non</span>@endif</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.grading.systems.show', ['tenant' => $tenantCode, 'id' => $s->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.grading.systems.manage', ['tenant' => $tenantCode, 'id' => $s->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun système.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $systems->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Nouveau système</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div><label class="label">Nom</label><input class="input" wire:model="name">@error('name') <span class="text-error">{{ $message }}</span> @enderror</div>
                        <div><label class="label">Code</label><input class="input" wire:model="code"></div>
                        <div><label class="label">Base (ex. 20)</label><input class="input" type="number" step="0.01" wire:model="scaleBase"></div>
                        <div class="form-span-2"><label class="label">Description</label><textarea class="input" rows="2" wire:model="description"></textarea></div>
                        <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="isActive"> Actif</label></div>
                    </div>
                </div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
