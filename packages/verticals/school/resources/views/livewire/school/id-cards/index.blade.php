<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Cartes ID</h2>
            <div class="sch-list-head__actions">
                @if ($batchCode && $tenantCode)
                    <a class="btn btn-secondary btn-sm"
                       href="{{ route('tenant.school.id_cards.print', array_filter(['tenant' => $tenantCode, 'batch' => $batchCode, 'year' => $academicYearId])) }}"
                       onclick="return schoolOpenPrint(this.href)">
                        Imprimer le lot
                    </a>
                @endif
                @if($canManage ?? true)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">Générer des cartes</button>
                @endif
            </div>
        </div>

        <div class="sch-filters" style="flex-wrap:wrap;">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Élève ou code-barres…">
            <select class="input" wire:model.live="filterYearId" style="max-width:180px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)
                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="listClassId" style="max-width:160px;">
                <option value="">Toutes classes</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="listSection" style="max-width:130px;">
                <option value="">Sections</option>
                @foreach($sections as $opt)
                    <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="listGender" style="max-width:120px;">
                <option value="">Genres</option>
                @foreach($genders as $opt)
                    <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                @endforeach
            </select>
            <select class="input" wire:model.live="listStatus" style="max-width:150px;">
                <option value="">Statuts</option>
                @foreach($statuses as $opt)
                    <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                @endforeach
            </select>
            <input class="input" type="search" wire:model.live.debounce.300ms="filterBatch" placeholder="Filtrer par lot…" style="max-width:160px;">
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Année</th>
                        <th>Code-barres</th>
                        <th>Lot</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cards as $c)
                        <tr>
                            <td>
                                <strong>{{ $c->student?->student_code }}</strong>
                                — {{ $c->student?->first_name }} {{ $c->student?->last_name }}
                            </td>
                            <td>{{ $c->academicYear?->name ?? '—' }}</td>
                            <td>{{ $c->barcode_data ?? '—' }}</td>
                            <td>{{ $c->batch_code ?? '—' }}</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.id_cards.show', ['tenant' => $tenantCode, 'id' => $c->id]) }}">Voir</a>
                                    @if($canManage ?? true)
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.id_cards.manage', ['tenant' => $tenantCode, 'id' => $c->id]) }}">Gérer</a>
                                    @endif
                                    <a class="btn btn-secondary btn-sm"
                                       href="{{ route('tenant.school.id_cards.print', ['tenant' => $tenantCode, 'id' => $c->id]) }}"
                                       onclick="return schoolOpenPrint(this.href)">Imprimer</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune carte.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $cards->links() }}</div>
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal sch-modal--wide" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">Générer des cartes ID</h3>
                        <p class="sch-modal__hint">Unitaire ou lot selon les filtres d’inscription.</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    @unless($editingId)
                        <div style="display:flex; gap:8px; margin-bottom:14px;">
                            <button type="button" class="btn {{ $mode === 'single' ? 'btn-primary' : 'btn-secondary' }} btn-sm" wire:click="$set('mode', 'single')">Unitaire</button>
                            <button type="button" class="btn {{ $mode === 'batch' ? 'btn-primary' : 'btn-secondary' }} btn-sm" wire:click="$set('mode', 'batch')">Lot (batch)</button>
                        </div>
                    @endunless

                    <div class="form-grid">
                        <div>
                            <label class="label">Année académique</label>
                            <select class="input" wire:model="academicYearId">
                                <option value="">—</option>
                                @foreach($years as $y)
                                    <option value="{{ $y->id }}">{{ $y->name }}</option>
                                @endforeach
                            </select>
                            @error('academicYearId') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Code lot {{ $mode === 'batch' && ! $editingId ? '' : '(optionnel)' }}</label>
                            <input class="input" type="text" wire:model="batchCode" placeholder="ex: Promo-2026-A">
                        </div>

                        @if ($mode === 'single' || $editingId)
                            <div class="form-span-2">
                                @include('school::livewire.partials.searchable-student')
                            </div>
                        @else
                            <div>
                                <label class="label">Classe</label>
                                <select class="input" wire:model="filterClassId">
                                    <option value="">Toutes</option>
                                    @foreach($classes as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label">Section</label>
                                <select class="input" wire:model="filterSection">
                                    <option value="">Toutes</option>
                                    @foreach ($sections as $opt)
                                        <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label">Genre</label>
                                <select class="input" wire:model="filterGender">
                                    <option value="">Tous</option>
                                    @foreach ($genders as $opt)
                                        <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label">Statut inscription</label>
                                <select class="input" wire:model="filterStatus">
                                    <option value="">Tous</option>
                                    @foreach ($statuses as $opt)
                                        <option value="{{ $opt->value }}">{{ $opt->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="sch-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="save">
                        {{ ($mode === 'batch' && ! $editingId) ? 'Générer le lot' : 'Enregistrer' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
