<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">Paramétrage école</h2>
                @if($isHoursTab)
                    <p class="sch-modal__hint" style="margin:4px 0 0;">Début et fermeture de la journée, plus les deux pauses (récréation et pause de midi).</p>
                @elseif($isPeriodGroup)
                    <p class="sch-modal__hint" style="margin:4px 0 0;">Ces tranches forment les lignes de l’emploi du temps (ex. 1ère heure 07:30–08:20).</p>
                @elseif($activeGroup === \School\Support\SchoolOptionCatalog::GROUP_DOCUMENT_TYPE)
                    <p class="sch-modal__hint" style="margin:4px 0 0;">Liste des pièces demandées aux familles. L’acte de naissance et la pièce d’identité du parent restent obligatoires.</p>
                @endif
            </div>
            @unless($isHoursTab)
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="seedDefaults">Valeurs par défaut</button>
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">
                    {{ $isPeriodGroup ? 'Nouvelle tranche' : 'Nouvelle option' }}
                </button>
            </div>
            @endunless
        </div>

        <div class="sch-filters" style="padding-top: 8px;">
            <div style="display:flex; gap:6px; flex-wrap:wrap; width:100%; margin-bottom:8px;">
                @foreach($groups as $key => $meta)
                    <button type="button"
                            class="btn btn-sm {{ $activeGroup === $key ? 'btn-primary' : 'btn-secondary' }}"
                            wire:click="setGroup('{{ $key }}')">
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </div>
            @unless($isHoursTab)
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $isPeriodGroup ? 'Rechercher une tranche…' : 'Rechercher valeur ou libellé…' }}">
            <select class="input" wire:model.live="filterActive" style="max-width:160px;">
                <option value="">Tous les statuts</option>
                <option value="1">Actives</option>
                <option value="0">Inactives</option>
            </select>
            @endunless
        </div>

        @if($isHoursTab)
            <div style="padding:4px 16px 20px;">
                <div class="form-grid">
                    <div>
                        <label class="label">Début des cours</label>
                        <input class="input" type="time" wire:model="dayStart">
                        @error('dayStart') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Fermeture des cours</label>
                        <input class="input" type="time" wire:model="dayEnd">
                        @error('dayEnd') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Durée d’une heure de cours</label>
                        <input class="input" type="number" min="20" max="180" wire:model="lessonMinutes">
                        <p class="sch-modal__hint" style="margin:6px 0 0;">En minutes (souvent 50).</p>
                        @error('lessonMinutes') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div></div>
                    <div>
                        <label class="label">1ère pause — durée</label>
                        <input class="input" type="number" min="0" max="180" wire:model="break1Minutes">
                        <p class="sch-modal__hint" style="margin:6px 0 0;">Minutes (récréation).</p>
                        @error('break1Minutes') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">1ère pause — après</label>
                        <select class="input" wire:model="break1After">
                            @for($n = 1; $n <= 8; $n++)
                                <option value="{{ $n }}">la {{ $n === 1 ? '1ère' : $n.'e' }} heure</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="label">2e pause — durée</label>
                        <input class="input" type="number" min="0" max="180" wire:model="break2Minutes">
                        <p class="sch-modal__hint" style="margin:6px 0 0;">Minutes (pause de midi).</p>
                        @error('break2Minutes') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">2e pause — après</label>
                        <select class="input" wire:model="break2After">
                            @for($n = 1; $n <= 8; $n++)
                                <option value="{{ $n }}">la {{ $n === 1 ? '1ère' : $n.'e' }} heure</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:16px;">
                    <button type="button" class="btn btn-primary" wire:click="saveHours">Enregistrer les horaires</button>
                    <button type="button" class="btn btn-secondary" wire:click="generatePeriodsFromHours" wire:confirm="Recalculer les tranches de cours à partir de ces horaires ? Les tranches actuelles seront remplacées.">Générer les tranches</button>
                </div>
            </div>
        @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Ordre</th>
                        @if($isPeriodGroup)
                            <th>Libellé</th>
                            <th>Début</th>
                            <th>Fin</th>
                        @else
                            <th>Valeur</th>
                            <th>Libellé</th>
                        @endif
                        <th>Active</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($options as $opt)
                        @php
                            $period = $isPeriodGroup ? \School\Support\SchoolTimetable::parsePeriodValue((string) $opt->value) : null;
                        @endphp
                        <tr>
                            <td>{{ $opt->sort_order }}</td>
                            @if($isPeriodGroup)
                                <td><strong>{{ $opt->label }}</strong></td>
                                <td>{{ $period['start'] ?? $opt->value }}</td>
                                <td>{{ $period['end'] ?? '—' }}</td>
                            @else
                                <td><code>{{ $opt->value }}</code></td>
                                <td><strong>{{ $opt->label }}</strong></td>
                            @endif
                            <td>
                                @if($opt->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    @if($isPeriodGroup)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="edit({{ $opt->id }})">Modifier</button>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $opt->id }})" wire:confirm="Retirer cette tranche ?">Retirer</button>
                                    @else
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.options.show', ['tenant' => $tenantCode, 'id' => $opt->id]) }}">Voir</a>
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.options.manage', ['tenant' => $tenantCode, 'id' => $opt->id]) }}">Gérer</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isPeriodGroup ? 6 : 5 }}">
                                {{ $isPeriodGroup ? 'Aucune tranche. Cliquez « Valeurs par défaut » ou créez la 1ère heure.' : 'Aucune option dans ce groupe.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $options->links() }}</div>
        @endif
    </section>

    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel">
            <div class="sch-modal" role="dialog" aria-modal="true">
                <div class="sch-modal__head">
                    <div>
                        <h3 class="sch-modal__title">
                            @if($isPeriodGroup)
                                {{ $editingId ? 'Modifier la tranche' : 'Nouvelle tranche' }}
                            @else
                                {{ $editingId ? 'Modifier l’option' : 'Nouvelle option' }}
                            @endif
                        </h3>
                        <p class="sch-modal__hint">{{ $groups[$activeGroup]['hint'] ?? ($groups[$activeGroup]['label'] ?? $activeGroup) }}</p>
                    </div>
                    <button type="button" class="sch-modal__close" wire:click="cancel" aria-label="Fermer">&times;</button>
                </div>
                <div class="sch-modal__body">
                    <div class="form-grid">
                        @if($isPeriodGroup)
                            <div class="form-span-2">
                                <label class="label">Libellé</label>
                                <input class="input" type="text" wire:model="label" placeholder="1ère heure">
                                @error('label') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="label">Début</label>
                                <input class="input" type="time" wire:model="periodStart">
                                @error('periodStart') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="label">Fin</label>
                                <input class="input" type="time" wire:model="periodEnd">
                                @error('periodEnd') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div>
                                <label class="label">Valeur (clé)</label>
                                <input class="input" type="text" wire:model="value" placeholder="ex: male">
                                @error('value') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="label">Libellé</label>
                                <input class="input" type="text" wire:model="label" placeholder="ex: Masculin">
                                @error('label') <span class="text-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="label">Ordre</label>
                            <input class="input" type="number" wire:model="sortOrder" min="0">
                            @error('sortOrder') <span class="text-error">{{ $message }}</span> @enderror
                        </div>
                        <div style="display:flex; align-items:flex-end;">
                            <label class="label" style="margin:0;">
                                <input type="checkbox" wire:model="isActive"> Active
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
