<div class="page-body crm-page crm-opp-page">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    <div class="crm-opp-shell {{ $selected ? 'has-selection' : '' }}">
        <div class="crm-opp-main">
            <header class="crm-opp-toolbar">
                <div class="crm-opp-toolbar__search">
                    <input class="input"
                           type="search"
                           wire:model.live.debounce.250ms="boardSearch"
                           placeholder="Rechercher une entreprise, contact, téléphone…"
                           aria-label="Rechercher">
                </div>
                <div class="crm-opp-toolbar__filters">
                    <div class="crm-act-filters__field--picker crm-opp-owner-picker">
                        @if ($ownerFilter !== '')
                            <div class="crm-picker-selected">
                                <span>{{ $ownerLabel }}</span>
                                <button type="button" class="crm-picker-selected__clear" wire:click="clearOwnerFilter" title="Afficher tous" aria-label="Afficher tous">×</button>
                            </div>
                        @else
                            <input
                                class="input"
                                type="search"
                                placeholder="Rechercher Commercial"
                                wire:model.live.debounce.250ms="ownerSearch"
                                autocomplete="off"
                                aria-label="Rechercher Commercial"
                            >
                            <div wire:loading wire:target="ownerSearch" class="crm-picker-hint">Recherche…</div>
                            @if (mb_strlen(trim($ownerSearch)) >= 2 && count($ownerResults) === 0)
                                <p class="crm-picker-hint" wire:loading.remove wire:target="ownerSearch">Aucun commercial.</p>
                            @endif
                            @if (count($ownerResults) > 0)
                                <div class="crm-picker-results" role="listbox">
                                    <button type="button" class="crm-picker-results__item crm-picker-results__item--all" wire:click="clearOwnerFilter">
                                        <strong>Tous les commerciaux</strong>
                                        <span>Afficher tout le pipeline</span>
                                    </button>
                                    @foreach ($ownerResults as $row)
                                        <button type="button" class="crm-picker-results__item" wire:key="own-{{ $row['id'] }}" wire:click="selectOwnerFilter({{ $row['id'] }})">
                                            <strong>{{ $row['name'] }}</strong>
                                            @if ($row['meta'] !== '')
                                                <span>{{ $row['meta'] }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </header>

            <div class="crm-opp-meta">
                <span class="crm-opp-meta__item">
                    <strong>{{ collect($items)->flatten(1)->count() }}</strong> cartes
                </span>
                <span class="crm-opp-meta__dot" aria-hidden="true">·</span>
                <span class="crm-opp-meta__item">
                    CA potentiel <strong>{{ number_format($pipelineValue, 0, ',', ' ') }}</strong>
                </span>
                @if ($selected)
                    <span class="crm-opp-meta__hint">Fiche ouverte à droite</span>
                @endif
            </div>

            <div class="crm-kanban-scroll">
                <div class="crm-kanban">
                    @foreach ($columns as $status => $meta)
                        @php $colItems = $items[$status] ?? collect(); @endphp
                        <section class="crm-kanban__col crm-kanban__col--{{ $meta['tone'] }}">
                            <header class="crm-kanban__head">
                                <div class="crm-kanban__head-left">
                                    <h3 class="crm-kanban__title">{{ $meta['label'] }}</h3>
                                    <span class="crm-kanban__count">{{ $colItems->count() }}</span>
                                </div>
                                <span class="crm-kanban__total">{{ number_format($columnTotals[$status] ?? 0, 0, ',', ' ') }}</span>
                            </header>
                            <p class="crm-kanban__hint">{{ $meta['hint'] }}</p>

                            <div class="crm-kanban__cards">
                                @forelse ($colItems as $prospect)
                                    @php
                                        $next = $prospect->nextPlannedActivity;
                                        $isSelected = $selectedProspectId === $prospect->id;
                                        $ownerName = $prospect->owner?->name ?? '';
                                        $initials = $ownerName !== ''
                                            ? collect(preg_split('/\s+/', trim($ownerName)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')
                                            : '?';
                                        $nextTone = $next
                                            ? ($next->isOverdue() ? 'danger' : ($next->isDueToday() ? 'today' : 'ok'))
                                            : null;
                                        $isHot = $next && ($next->isDueToday() || $next->isOverdue());
                                    @endphp
                                    <article
                                        class="crm-card crm-card--{{ $meta['tone'] }} {{ $isSelected ? 'is-selected' : '' }} {{ $nextTone === 'danger' ? 'crm-card--overdue' : '' }}"
                                        wire:key="opp-{{ $prospect->id }}"
                                        wire:click="selectProspect({{ $prospect->id }})"
                                        role="button"
                                        tabindex="0"
                                    >
                                        <div class="crm-card__top">
                                            <div class="crm-card__identity">
                                                <span class="crm-card__name">{{ $prospect->name }}</span>
                                                <p class="crm-card__meta">
                                                    @if ($prospect->phone)
                                                        {{ $prospect->phone }}
                                                    @else
                                                        {{ $prospect->reference }}
                                                    @endif
                                                </p>
                                            </div>
                                            @if ($prospect->expected_value)
                                                <div class="crm-card__value">{{ number_format((float) $prospect->expected_value, 0, ',', ' ') }}</div>
                                            @endif
                                        </div>

                                        <div class="crm-card__mid">
                                            <span class="crm-pill crm-pill--soft">{{ \InovCom\Prospects\Models\Prospect::sourceLabel($prospect->source) }}</span>
                                            @if ($isHot)
                                                <span class="crm-pill crm-pill--hot">Hot</span>
                                            @endif
                                        </div>

                                        <div class="crm-card__bottom">
                                            <div class="crm-card__activity">
                                                @if ($next)
                                                    <span class="crm-card__next-line crm-card__next-line--{{ $nextTone }}">
                                                        {{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($next->type) }}
                                                        · {{ $next->due_at?->format('d/m H:i') ?? 'à planifier' }}
                                                    </span>
                                                @elseif ($status === \InovCom\Prospects\Models\Prospect::STATUS_QUALIFIE)
                                                    <span class="crm-card__next-line">À faire avancer</span>
                                                @else
                                                    <span class="crm-card__next-line is-muted">Pas d’action</span>
                                                @endif
                                            </div>
                                            <span class="crm-avatar" title="{{ $ownerName ?: 'Non assigné' }}">{{ $initials }}</span>
                                        </div>
                                    </article>
                                @empty
                                    <p class="crm-kanban__empty">Aucune carte</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="crm-opp-side" aria-label="Détail opportunité">
            @if ($selected)
                <div class="crm-opp-side__panel" wire:key="panel-{{ $selected->id }}">
                    @include('inovcom-prospects::partials.prospect-drawer', [
                        'prospect' => $selected,
                        'canManage' => $canManage,
                        'canConvert' => $canConvert,
                        'showPanelActions' => $showPanelActions,
                        'compact' => false,
                    ])
                </div>
            @else
                <div class="crm-opp-side__empty">
                    <div class="crm-opp-side__empty-icon" aria-hidden="true">◇</div>
                    <h3>Fiche opportunité</h3>
                    <p>Sélectionnez une carte du pipeline pour voir le contact, la prochaine action et l’historique.</p>
                </div>
            @endif
        </aside>
    </div>

    @if ($showAssignModal)
        <div class="crm-modal-backdrop" wire:click.self="closeAssignModal">
            <div class="crm-modal" role="dialog" aria-modal="true">
                <div class="crm-modal__head">
                    <div>
                        <h3 class="crm-modal__title">Assigner un commercial</h3>
                        <p class="crm-modal__sub">{{ $modalProspectName }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeAssignModal">Fermer</button>
                </div>
                <div class="field">
                    <label class="field-label">Commercial responsable</label>
                    <select class="input" wire:model="assignOwnerId">
                        <option value="">— Non assigné —</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="crm-modal__actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeAssignModal">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveAssign">Enregistrer</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showScheduleModal)
        <div class="crm-modal-backdrop" wire:click.self="closeScheduleModal">
            <div class="crm-modal" role="dialog" aria-modal="true">
                <div class="crm-modal__head">
                    <div>
                        <h3 class="crm-modal__title">Planifier la prochaine action</h3>
                        <p class="crm-modal__sub">{{ $modalProspectName }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeScheduleModal">Fermer</button>
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                    <div class="field">
                        <label class="field-label">Type</label>
                        <select class="input" wire:model="scheduleType">
                            @foreach ($actionTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Échéance</label>
                        <input class="input" type="datetime-local" wire:model="scheduleDueAt">
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Résumé</label>
                        <input class="input" wire:model="scheduleSummary" placeholder="Ex. Rappeler pour devis">
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Détail</label>
                        <textarea class="input" rows="2" wire:model="scheduleBody"></textarea>
                    </div>
                    <div class="field" style="grid-column:1 / -1;">
                        <label class="field-label">Assigné à</label>
                        <select class="input" wire:model="scheduleAssigneeId">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="crm-modal__actions">
                    <button type="button" class="btn btn-secondary" wire:click="closeScheduleModal">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveSchedule">Planifier</button>
                </div>
            </div>
        </div>
    @endif
</div>
