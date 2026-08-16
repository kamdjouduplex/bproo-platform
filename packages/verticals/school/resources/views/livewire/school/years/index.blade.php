<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Années académiques</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">Nouvelle année</button>
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher nom ou code…">
            <select class="input" wire:model.live="filterActive" style="max-width:180px;">
                <option value="">Tous les statuts</option>
                <option value="1">Actives</option>
                <option value="0">Inactives</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Code</th>
                        <th>Dates</th>
                        <th>Active</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($years as $y)
                        <tr>
                            <td><strong>{{ $y->name }}</strong></td>
                            <td>{{ $y->code ?? '—' }}</td>
                            <td>
                                {{ $y->start_date?->format('d/m/Y') ?? '—' }}
                                →
                                {{ $y->end_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td>
                                @if($y->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.years.show', ['tenant' => $tenantCode, 'id' => $y->id]) }}">Voir</a>
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.years.manage', ['tenant' => $tenantCode, 'id' => $y->id]) }}">Gérer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune année.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $years->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Nouvelle année académique</h3>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        <div>
                            <label class="label">Code (optionnel)</label>
                            <input class="input" type="text" wire:model="code" placeholder="2026-2027">
                            @error('code') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Nom</label>
                            <input class="input" type="text" wire:model="name" placeholder="Année académique 2026-2027">
                            @error('name') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Début</label>
                            <input class="input" type="date" wire:model="startDate">
                            @error('startDate') <span class="text-error">{{ $message }}</span> @enderror
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

                        @if($priorYears->isNotEmpty())
                            <div class="form-span-2" style="margin-top:8px; padding-top:12px; border-top:1px solid #e2e8f0;">
                                <label class="label">Réutiliser depuis une année existante</label>
                                <p style="margin:0 0 8px; font-size:12px; color:#64748b;">
                                    Les classes, matières et enseignants ne sont pas dupliqués — seuls les paramétrages liés à l’année sont repris.
                                </p>
                                <select class="input" wire:model="carryFromYearId">
                                    <option value="">— Ne rien reprendre —</option>
                                    @foreach($priorYears as $py)
                                        <option value="{{ $py->id }}">{{ $py->name }}{{ $py->is_active ? ' (active)' : '' }}</option>
                                    @endforeach
                                </select>
                                <div style="display:flex; flex-wrap:wrap; gap:10px 16px; margin-top:10px; font-size:13px;">
                                    <label><input type="checkbox" wire:model="carryFees"> Structures de frais</label>
                                    <label><input type="checkbox" wire:model="carryGrading"> Règles de notation</label>
                                    <label><input type="checkbox" wire:model="carryCoefficients"> Coefficients matières</label>
                                    <label><input type="checkbox" wire:model="carryExams"> Sessions d’examens (brouillons)</label>
                                </div>
                            </div>
                        @endif
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
