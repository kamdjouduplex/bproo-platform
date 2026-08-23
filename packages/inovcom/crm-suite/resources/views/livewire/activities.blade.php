<div class="page-body crm-v2">
@include('inovcom-crm::partials.styles')
    @if (session('success'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>@endif

    <div class="crm-v2-head">
        <div>
            <h2>Activités</h2>
            <p>Appels, relances, rendez-vous — marquez comme terminé puis définissez la prochaine action.</p>
        </div>
        <div class="crm-v2-actions">
            @if ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="openCreateModal">+ Nouvelle activité</button>
            @endif
        </div>
    </div>

    <div class="crm-stat-grid">
        <article class="crm-stat crm-stat--rose"><div class="crm-stat__label">En retard</div><div class="crm-stat__value">{{ $counts['overdue'] }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--orange"><div class="crm-stat__label">Aujourd’hui</div><div class="crm-stat__value">{{ $counts['today'] }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--green"><div class="crm-stat__label">À venir</div><div class="crm-stat__value">{{ $counts['upcoming'] }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--blue"><div class="crm-stat__label">Terminées{{ $periodLabel ? ' ('.$periodLabel.')' : '' }}</div><div class="crm-stat__value">{{ $counts['done'] }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--violet"><div class="crm-stat__label">Total</div><div class="crm-stat__value">{{ $counts['all'] }}</div><span class="crm-stat__bar"></span></article>
    </div>

    <div class="crm-toolbar">
        <div class="crm-toolbar__search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher une activité, un prospect…">
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-act-type">Type</label>
            <select id="crm-act-type" class="input" wire:model.live="type">
                <option value="">Tous</option>
                @foreach ($typeOptions as $k => $lab)
                    <option value="{{ $k }}">{{ $lab }}</option>
                @endforeach
            </select>
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-act-period">Période</label>
            <select id="crm-act-period" class="input" wire:model.live="period">
                <option value="all">Toute période</option>
                <option value="today">Aujourd’hui</option>
                <option value="7">7 derniers jours</option>
                <option value="30">30 derniers jours</option>
                <option value="90">90 derniers jours</option>
                <option value="custom">Personnalisée</option>
            </select>
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-act-from">Du</label>
            <input id="crm-act-from" class="input" type="date" wire:model.live="dateFrom">
        </div>
        <div class="crm-toolbar__field">
            <label for="crm-act-to">Au</label>
            <input id="crm-act-to" class="input" type="date" wire:model.live="dateTo">
        </div>
        <div class="crm-toolbar__actions">
            <button type="button" class="crm-btn-icon" wire:click="resetFilters" title="Réinitialiser" aria-label="Réinitialiser les filtres">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.3"/><polyline points="21 3 21 9 15 9"/></svg>
            </button>
        </div>
    </div>

    <div class="crm-fiche-grid">
        <div>
            <div class="crm-tabs" role="tablist">
                @foreach (['all'=>'Toutes','overdue'=>'En retard','today'=>'Aujourd’hui','upcoming'=>'À venir','done'=>'Terminées'] as $key => $lab)
                    <button type="button" class="{{ $scope === $key ? 'is-on' : '' }}" wire:click="setScope('{{ $key }}')">{{ $lab }}</button>
                @endforeach
            </div>
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Activité</th>
                            <th>Concerné</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Responsable</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($activities as $act)
                        <tr>
                            <td>
                                <strong>{{ $act->displayTitle() }}</strong>
                                @if ($act->result)<div class="crm-act-row__meta">{{ $act->result }}</div>@endif
                            </td>
                            <td>
                                @if ($act->prospect)
                                    <a href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $act->prospect_id]) }}">{{ $act->prospect->companyDisplayName() }}</a>
                                @else — @endif
                            </td>
                            <td><span class="crm-badge crm-badge--violet">{{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($act->type) }}</span></td>
                            <td>{{ $act->due_at?->format('d/m/Y H:i') ?? $act->created_at?->format('d/m/Y') }}</td>
                            <td>
                                <div class="crm-person">
                                    <span class="crm-avatar crm-avatar--sm">{{ mb_strtoupper(mb_substr($act->assignee?->name ?? $act->user?->name ?? '?', 0, 1)) }}</span>
                                    <span>{{ $act->assignee?->name ?? $act->user?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td><span class="crm-badge crm-badge--{{ $act->calendarTone() === 'rose' ? 'rose' : ($act->calendarTone() === 'orange' ? 'orange' : ($act->calendarTone() === 'green' ? 'green' : 'blue')) }}">{{ $act->calendarLabel() }}</span></td>
                            <td>
                                @if ($act->isPlanned())
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="openCompleteModal({{ $act->id }})">Terminer</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="crm-empty">Aucune activité sur ce filtre.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;">{{ $activities->links() }}</div>
        </div>
        <div class="crm-side-stack">
            <section class="crm-card">
                <h3 class="crm-card__title">Calendrier du jour</h3>
                <div class="crm-act-row__meta" style="margin-bottom:8px;">{{ now()->translatedFormat('l d F Y') }}</div>
                @forelse ($todayAgenda as $item)
                    <div class="crm-act-row">
                        <div class="crm-act-row__time">{{ $item->due_at?->format('H:i') }}</div>
                        <div>
                            <strong>{{ $item->displayTitle() }}</strong>
                            <div class="crm-act-row__meta">{{ $item->prospect?->name }}</div>
                        </div>
                    </div>
                @empty
                    <p class="crm-empty">Rien de prévu aujourd’hui.</p>
                @endforelse
            </section>
            @if ($nextAction)
                <section class="crm-next-card">
                    <h3>Prochaine action</h3>
                    <strong>{{ $nextAction->displayTitle() }}</strong>
                    <div class="crm-act-row__meta">{{ $nextAction->due_at?->format('d/m/Y H:i') }} · {{ $nextAction->prospect?->companyDisplayName() }}</div>
                    <button type="button" class="btn btn-success" style="margin-top:12px;width:100%;" wire:click="openCompleteModal({{ $nextAction->id }})">Marquer comme terminée</button>
                </section>
            @endif
            <section class="crm-card">
                <h3 class="crm-card__title">Rappels</h3>
                <div class="crm-source-row"><span>En retard</span><strong>{{ $counts['overdue'] }}</strong></div>
                <div class="crm-source-row"><span>Aujourd’hui</span><strong>{{ $counts['today'] }}</strong></div>
                <div class="crm-source-row"><span>À venir</span><strong>{{ $counts['upcoming'] }}</strong></div>
            </section>
        </div>
    </div>

@if ($showCompleteModal)
<div class="crm-modal-backdrop" wire:click="closeCompleteModal">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head">
            <h3 class="crm-modal__title">Activité terminée</h3>
            <p class="crm-modal__sub">Indiquez le résultat et la prochaine action — obligatoire.</p>
        </div>
        <div class="field"><label class="field-label">Résultat</label><input class="input" wire:model="completeResult" placeholder="Ex. Client intéressé"></div>
        <div class="field"><label class="field-label">Prochaine action *</label><input class="input" wire:model="completeNextSummary" placeholder="Ex. Démonstration">@error('completeNextSummary')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="field-label">Type</label>
            <select class="input" wire:model="completeNextType">
                @foreach ($actionTypes as $k => $lab)<option value="{{ $k }}">{{ $lab }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label class="field-label">Date *</label><input class="input" type="datetime-local" wire:model="completeNextDue"></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="closeCompleteModal">Annuler</button>
            <button type="button" class="btn btn-primary" wire:click="saveComplete">Enregistrer</button>
        </div>
    </div>
</div>
@endif

@if ($showCreateModal)
<div class="crm-modal-backdrop" wire:click="$set('showCreateModal', false)">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head"><h3 class="crm-modal__title">Nouvelle activité</h3></div>
        <div class="field"><label class="field-label">Prospect *</label>
            @if ($newProspectId)
                <div>{{ $newProspectLabel }}</div>
            @else
                <input class="input" wire:model.live.debounce.250ms="newProspectSearch" placeholder="Rechercher…">
                @foreach ($newProspectResults as $row)
                    <button type="button" class="crm-picker-results__item" wire:click="selectNewProspect({{ $row['id'] }})"><strong>{{ $row['name'] }}</strong></button>
                @endforeach
            @endif
        </div>
        <div class="field"><label class="field-label">Type</label>
            <select class="input" wire:model="newType">@foreach ($actionTypes as $k=>$lab)<option value="{{ $k }}">{{ $lab }}</option>@endforeach</select>
        </div>
        <div class="field"><label class="field-label">Objet *</label><input class="input" wire:model="newSummary"></div>
        <div class="field"><label class="field-label">Quand *</label><input class="input" type="datetime-local" wire:model="newDueAt"></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="$set('showCreateModal', false)">Annuler</button>
            <button type="button" class="btn btn-primary" wire:click="saveCreate">Planifier</button>
        </div>
    </div>
</div>
@endif
</div>
