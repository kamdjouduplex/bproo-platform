@php
    use InovCom\Prospects\Models\ProspectActivity;
@endphp

<div class="page-body crm-page">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    <div class="crm-page__intro">
        <div>
            <h2 class="crm-page__title">Activités</h2>
            <p class="crm-page__lead">Suivez les actions planifiées et l’historique commercial.</p>
        </div>
        @if ($hasActiveFilters)
            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
        @endif
    </div>

    <section class="crm-act-filters">
        <div class="crm-act-filters__search">
            <label class="crm-act-filters__field crm-act-filters__field--search">
                <span class="crm-act-filters__label">Recherche</span>
                <input
                    class="input"
                    type="search"
                    placeholder="Prospect, référence, résumé, note…"
                    wire:model.live.debounce.300ms="search"
                    aria-label="Rechercher une activité"
                >
            </label>
        </div>

        <div class="crm-scope-tabs" role="tablist" aria-label="Vue rapide">
            <button type="button" role="tab" aria-selected="{{ $scope === 'all' ? 'true' : 'false' }}" class="crm-scope-tab {{ $scope === 'all' ? 'is-active' : '' }}" wire:click="setScope('all')">
                Tout <span>{{ $counts['all'] }}</span>
            </button>
            <button type="button" role="tab" aria-selected="{{ $scope === 'planned' ? 'true' : 'false' }}" class="crm-scope-tab {{ $scope === 'planned' ? 'is-active' : '' }}" wire:click="setScope('planned')">
                Planifiées <span>{{ $counts['planned'] }}</span>
            </button>
            <button type="button" role="tab" aria-selected="{{ $scope === 'overdue' ? 'true' : 'false' }}" class="crm-scope-tab crm-scope-tab--warn {{ $scope === 'overdue' ? 'is-active' : '' }}" wire:click="setScope('overdue')">
                En retard <span>{{ $counts['overdue'] }}</span>
            </button>
            <button type="button" role="tab" aria-selected="{{ $scope === 'mine' ? 'true' : 'false' }}" class="crm-scope-tab {{ $scope === 'mine' ? 'is-active' : '' }}" wire:click="setScope('mine')">
                Mes activités <span>{{ $counts['mine'] }}</span>
            </button>
            <button type="button" role="tab" aria-selected="{{ $scope === 'done' ? 'true' : 'false' }}" class="crm-scope-tab {{ $scope === 'done' ? 'is-active' : '' }}" wire:click="setScope('done')">
                Terminées <span>{{ $counts['done'] }}</span>
            </button>
        </div>

        <div class="crm-act-filters__grid">
            <label class="crm-act-filters__field">
                <span class="crm-act-filters__label">Type</span>
                <select class="input" wire:model.live="type">
                    <option value="">Tous</option>
                    @foreach ($typeOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            @if ($showStateFilter)
                <label class="crm-act-filters__field">
                    <span class="crm-act-filters__label">État</span>
                    <select class="input" wire:model.live="state">
                        <option value="">Tous</option>
                        @foreach ($stateOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="crm-act-filters__field crm-act-filters__field--picker">
                <span class="crm-act-filters__label">Prospect</span>
                @if ($prospectFilter !== '')
                    <div class="crm-picker-selected">
                        <span>{{ $prospectLabel }}</span>
                        <button type="button" class="crm-picker-selected__clear" wire:click="clearProspectFilter" aria-label="Retirer">×</button>
                    </div>
                @else
                    <input
                        class="input"
                        type="search"
                        placeholder="Tapez 2 caractères…"
                        wire:model.live.debounce.250ms="prospectSearch"
                        autocomplete="off"
                    >
                    <div wire:loading wire:target="prospectSearch" class="crm-picker-hint">Recherche…</div>
                    @if (mb_strlen(trim($prospectSearch)) >= 2 && count($prospectResults) === 0)
                        <p class="crm-picker-hint" wire:loading.remove wire:target="prospectSearch">Aucun prospect.</p>
                    @endif
                    @if (count($prospectResults) > 0)
                        <div class="crm-picker-results" role="listbox">
                            @foreach ($prospectResults as $row)
                                <button type="button" class="crm-picker-results__item" wire:key="pf-{{ $row['id'] }}" wire:click="selectProspectFilter({{ $row['id'] }})">
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

            <div class="crm-act-filters__field crm-act-filters__field--picker">
                <span class="crm-act-filters__label">Commercial (carte)</span>
                @if ($ownerFilter !== '')
                    <div class="crm-picker-selected">
                        <span>{{ $ownerLabel }}</span>
                        <button type="button" class="crm-picker-selected__clear" wire:click="clearOwnerFilter" aria-label="Retirer">×</button>
                    </div>
                @else
                    <input
                        class="input"
                        type="search"
                        placeholder="Nom ou e-mail…"
                        wire:model.live.debounce.250ms="ownerSearch"
                        autocomplete="off"
                    >
                    <div wire:loading wire:target="ownerSearch" class="crm-picker-hint">Recherche…</div>
                    @if (mb_strlen(trim($ownerSearch)) >= 2 && count($ownerResults) === 0)
                        <p class="crm-picker-hint" wire:loading.remove wire:target="ownerSearch">Aucun commercial.</p>
                    @endif
                    @if (count($ownerResults) > 0)
                        <div class="crm-picker-results" role="listbox">
                            @foreach ($ownerResults as $row)
                                <button type="button" class="crm-picker-results__item" wire:key="of-{{ $row['id'] }}" wire:click="selectOwnerFilter({{ $row['id'] }})">
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

            <div class="crm-act-filters__field crm-act-filters__field--picker">
                <span class="crm-act-filters__label">Assigné à</span>
                @if ($assigneeFilter !== '')
                    <div class="crm-picker-selected">
                        <span>{{ $assigneeLabel }}</span>
                        <button type="button" class="crm-picker-selected__clear" wire:click="clearAssigneeFilter" aria-label="Retirer">×</button>
                    </div>
                @else
                    <input
                        class="input"
                        type="search"
                        placeholder="Nom ou e-mail…"
                        wire:model.live.debounce.250ms="assigneeSearch"
                        autocomplete="off"
                    >
                    <div wire:loading wire:target="assigneeSearch" class="crm-picker-hint">Recherche…</div>
                    @if (mb_strlen(trim($assigneeSearch)) >= 2 && count($assigneeResults) === 0)
                        <p class="crm-picker-hint" wire:loading.remove wire:target="assigneeSearch">Aucun utilisateur.</p>
                    @endif
                    @if (count($assigneeResults) > 0)
                        <div class="crm-picker-results" role="listbox">
                            @foreach ($assigneeResults as $row)
                                <button type="button" class="crm-picker-results__item" wire:key="af-{{ $row['id'] }}" wire:click="selectAssigneeFilter({{ $row['id'] }})">
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

            <label class="crm-act-filters__field">
                <span class="crm-act-filters__label">Du</span>
                <input class="input" type="date" wire:model.live="dateFrom">
            </label>

            <label class="crm-act-filters__field">
                <span class="crm-act-filters__label">Au</span>
                <input class="input" type="date" wire:model.live="dateTo">
            </label>
        </div>

        @if (count($activeChips) > 0)
            <div class="crm-act-filters__chips" aria-label="Filtres actifs">
                @foreach ($activeChips as $chip)
                    <button type="button" class="crm-filter-chip" wire:click="clearFilter('{{ $chip['key'] }}')" title="Retirer">
                        <span>{{ $chip['label'] }}</span>
                        <span class="crm-filter-chip__x" aria-hidden="true">×</span>
                    </button>
                @endforeach
                <button type="button" class="crm-filter-chip crm-filter-chip--clear" wire:click="resetFilters">Tout effacer</button>
            </div>
        @endif
    </section>

    <section class="crm-panel crm-act-list">
        <div class="crm-act-list__head">
            <div>
                <h3 class="crm-panel__title">Résultats</h3>
                <p class="crm-act-list__meta">
                    @if ($activities->total() === 0)
                        Aucune activité
                    @else
                        {{ $activities->firstItem() }}–{{ $activities->lastItem() }} sur {{ $activities->total() }}
                    @endif
                </p>
            </div>
            <label class="crm-act-list__perpage">
                <span>Par page</span>
                <select class="input input-sm" wire:model.live="perPage">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </label>
        </div>

        <div class="crm-activity-feed" wire:loading.class="is-loading">
            @forelse ($activities as $activity)
                @php
                    $isPlanned = $activity->state === ProspectActivity::STATE_PLANNED;
                    $overdue = $activity->isOverdue();
                    $dueToday = $activity->isDueToday();
                @endphp
                <article class="crm-act-row {{ $overdue ? 'crm-act-row--overdue' : '' }} {{ $dueToday && ! $overdue ? 'crm-act-row--today' : '' }}" wire:key="act-{{ $activity->id }}">
                    <div class="crm-act-row__icon crm-act-row__icon--{{ $activity->type }}" aria-hidden="true">
                        @switch($activity->type)
                            @case('call') ☎ @break
                            @case('email') ✉ @break
                            @case('meeting')
                            @case('reunion') ◎ @break
                            @case('demo') ▷ @break
                            @case('presentation') ◫ @break
                            @case('task') ✓ @break
                            @case('status') → @break
                            @default ·
                        @endswitch
                    </div>

                    <div class="crm-act-row__main">
                        <div class="crm-act-row__top">
                            <strong class="crm-act-row__title">{{ $activity->displayTitle() }}</strong>
                            <span class="crm-pill {{ $overdue ? 'crm-pill--hot' : ($isPlanned ? 'crm-pill--contacte' : ($activity->state === 'cancelled' ? 'crm-pill--converti' : 'crm-pill--soft')) }}">
                                {{ ProspectActivity::stateLabel($activity->state ?? 'done') }}
                            </span>
                        </div>

                        <div class="crm-act-row__sub">
                            <span class="crm-act-row__type">{{ $typeOptions[$activity->type] ?? $activity->type }}</span>
                            @if ($activity->prospect)
                                <span class="crm-act-row__sep">·</span>
                                @if (Route::has('tenant.prospects.show'))
                                    <a class="crm-act-row__prospect" href="{{ route('tenant.prospects.show', $activity->prospect) }}">{{ $activity->prospect->name }}</a>
                                @else
                                    <span class="crm-act-row__prospect">{{ $activity->prospect->name }}</span>
                                @endif
                            @endif
                        </div>

                        @if (filled($activity->body) && $activity->body !== $activity->displayTitle())
                            <p class="crm-act-row__body">{{ \Illuminate\Support\Str::limit($activity->body, 160) }}</p>
                        @endif

                        <div class="crm-act-row__meta">
                            @if ($activity->due_at)
                                <span class="{{ $overdue ? 'is-danger' : ($dueToday ? 'is-today' : '') }}">
                                    {{ $isPlanned ? 'Échéance' : 'Prévu' }} {{ $activity->due_at->format('d/m/Y H:i') }}
                                </span>
                            @else
                                <span>{{ $activity->created_at?->format('d/m/Y H:i') }}</span>
                            @endif
                            @if ($activity->assignee)
                                <span>· {{ $activity->assignee->name }}</span>
                            @elseif ($activity->user)
                                <span>· {{ $activity->user->name }}</span>
                            @endif
                            @if ($activity->prospect?->owner)
                                <span class="crm-muted">· carte {{ $activity->prospect->owner->name }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($isPlanned)
                        <div class="crm-act-row__actions">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="complete({{ $activity->id }})">Fait</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancel({{ $activity->id }})" wire:confirm="Annuler cette action ?">Annuler</button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="crm-act-empty">
                    <p>Aucune activité pour ces filtres.</p>
                    @if ($hasActiveFilters)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser les filtres</button>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($activities->total() > 0)
            <div class="crm-act-list__footer">
                {{ $activities->links('livewire.inovcom') }}
            </div>
        @endif
    </section>
</div>
