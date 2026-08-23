<div class="page-body crm-v2">
@include('inovcom-crm::partials.styles')
    @if (session('success'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>@endif
    @if ($boardMessage)<div class="alert alert-error" style="margin-bottom:14px;">{{ $boardMessage }}</div>@endif

    <div class="crm-v2-head">
        <div>
            <h2>Opportunités</h2>
            <p>Pipeline commercial — une opportunité ouverte = une prochaine action</p>
        </div>
        <div class="crm-v2-actions">
            @if ($canManage)
                <button type="button" class="btn btn-primary" wire:click="openCreateModal">+ Nouvelle opportunité</button>
            @endif
        </div>
    </div>

    <div class="crm-stat-grid">
        <article class="crm-stat crm-stat--blue"><div class="crm-stat__label">Total opportunités</div><div class="crm-stat__value">{{ $totalCount }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--violet"><div class="crm-stat__label">Valeur totale</div><div class="crm-stat__value">{{ fmt_money($pipelineValue) }}</div><div class="crm-stat__delta">{{ currency_label() }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--green"><div class="crm-stat__label">Valeur pondérée</div><div class="crm-stat__value">{{ fmt_money($weighted) }}</div><span class="crm-stat__bar"></span></article>
        <article class="crm-stat crm-stat--orange"><div class="crm-stat__label">Taux de conversion</div><div class="crm-stat__value">{{ number_format($conversion, 1, ',', ' ') }}%</div><span class="crm-stat__bar"></span></article>
    </div>

    <div class="crm-filterbar">
        <div class="crm-filterbar__search">
            <input class="input" type="search" wire:model.live.debounce.250ms="boardSearch" placeholder="Rechercher une opportunité ou une entreprise…">
        </div>
    </div>

    <div class="crm-kanban-scroll">
        <div class="crm-board">
            @foreach ($columns as $stage => $meta)
                @php $colItems = $items[$stage] ?? collect(); $tot = $columnTotals[$stage] ?? ['count'=>0,'amount'=>0]; @endphp
                <section class="crm-col crm-col--{{ $meta['tone'] }}" @if ($canManage) data-stage="{{ $stage }}" @endif>
                    <header class="crm-col__head">
                        <h3>{{ $meta['label'] }}</h3>
                        <div class="crm-col__meta">{{ $tot['count'] }} · {{ fmt_money($tot['amount']) }} {{ currency_label() }}</div>
                    </header>
                    <div class="crm-col__body">
                        @foreach ($colItems as $opp)
                            @php
                                $next = $opp->nextPlannedActivity;
                                $late = $next && $next->isOverdue();
                                $canDrag = $canManage && $opp->isOpen();
                            @endphp
                            <article class="crm-deal {{ $canDrag ? 'is-draggable' : '' }}"
                                     wire:key="opp-{{ $opp->id }}"
                                     @if ($canDrag) data-opp-id="{{ $opp->id }}" @endif>
                                <div class="crm-deal__title">{{ $opp->title }}</div>
                                <div class="crm-deal__co">{{ $opp->displayCompany() }}</div>
                                <div class="crm-deal__amt">{{ fmt_money($opp->amount) }} {{ currency_label() }}</div>
                                <div class="crm-deal__row">
                                    <span class="crm-badge {{ $opp->probabilityBand() === 'high' ? 'crm-badge--green' : ($opp->probabilityBand() === 'mid' ? 'crm-badge--orange' : 'crm-badge--rose') }}">{{ (int) $opp->probability }}%</span>
                                    <span>{{ $opp->owner?->name }}</span>
                                </div>
                                <div class="crm-deal__row">
                                    <span>{{ $opp->expected_close_date?->translatedFormat('d M') ?? '—' }}</span>
                                    @if ($late)
                                        <span class="crm-badge crm-badge--rose">Retard</span>
                                    @elseif ($next)
                                        <span>{{ $next->displayTitle() }}</span>
                                    @else
                                        <span class="crm-badge crm-badge--orange">Sans action</span>
                                    @endif
                                </div>
                                @if ($opp->isLost() && $opp->lost_reason)
                                    <div class="crm-deal__co">Raison : {{ \InovCom\Crm\Models\Opportunity::lostReasonOptions()[$opp->lost_reason] ?? $opp->lost_reason }}</div>
                                @endif
                                @if ($canManage && $opp->canRequestQuote() && $quotesEnabled)
                                    <button type="button" class="btn btn-primary btn-sm crm-deal__quote" draggable="false" wire:click="transferToErp({{ $opp->id }})">Demande de devis</button>
                                @endif
                                <div class="crm-deal__row">
                                    <a href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $opp->prospect_id]) }}" draggable="false">Fiche</a>
                                    @if ($canManage && $opp->isOpen())
                                        <button type="button" class="btn btn-secondary btn-sm" draggable="false" wire:click="openScheduleModal({{ $opp->id }})">Relancer</button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                        @if ($canManage && ! in_array($stage, ['gagne','perdu'], true))
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openCreateModal" data-no-drag>+ Ajouter</button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
    <div class="crm-legend">
        <span>Probabilité : 0–30% faible · 31–60% moyenne · 61–100% élevée</span>
        <span>Glisser-déposer pour changer d’étape. Perdue = motif obligatoire. Ouverte = prochaine action obligatoire.</span>
    </div>

@if ($showCreateModal)
<div class="crm-modal-backdrop" wire:click="closeCreateModal">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head"><h3 class="crm-modal__title">Nouvelle opportunité</h3></div>
        <div class="field"><label class="field-label">Prospect *</label>
            @if ($createProspectId)
                <div class="crm-picker-selected"><span>{{ $createProspectLabel }}</span></div>
            @else
                <input class="input" wire:model.live.debounce.250ms="createProspectSearch" placeholder="Rechercher un prospect…">
                @foreach ($createProspectResults as $row)
                    <button type="button" class="crm-picker-results__item" wire:click="selectCreateProspect({{ $row['id'] }})"><strong>{{ $row['name'] }}</strong><span>{{ $row['meta'] }}</span></button>
                @endforeach
            @endif
            @error('createProspectId')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field"><label class="field-label">Intitulé *</label><input class="input" wire:model="createTitle">@error('createTitle')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="field-label">Montant estimé</label><input class="input" type="number" min="0" wire:model="createAmount"></div>
        <div class="field"><label class="field-label">Date de décision</label><input class="input" type="date" wire:model="createCloseDate"></div>
        <div class="field"><label class="field-label">Commercial *</label>
            <select class="input" wire:model="createOwnerId">
                @foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label class="field-label">Prochaine action *</label><input class="input" wire:model="createNextSummary" placeholder="Ex. Appeler le DG">@error('createNextSummary')<span class="field-error">{{ $message }}</span>@enderror</div>
        <div class="field"><label class="field-label">Date / heure *</label><input class="input" type="datetime-local" wire:model="createNextDue"></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="closeCreateModal">Annuler</button>
            <button type="button" class="btn btn-primary" wire:click="saveCreate">Créer</button>
        </div>
    </div>
</div>
@endif

@if ($showLostModal)
<div class="crm-modal-backdrop" wire:click="closeLostModal">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head"><h3 class="crm-modal__title">Marquer perdue</h3><p class="crm-modal__sub">Le motif est obligatoire.</p></div>
        <div class="field"><label class="field-label">Raison *</label>
            <select class="input" wire:model="lostReason">
                <option value="">Choisir…</option>
                @foreach ($lostReasons as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            @error('lostReason')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field"><label class="field-label">Commentaire</label><textarea class="input" rows="3" wire:model="lostComment"></textarea></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="closeLostModal">Annuler</button>
            <button type="button" class="btn btn-danger" wire:click="saveLost">Confirmer</button>
        </div>
    </div>
</div>
@endif

@if ($showScheduleModal)
<div class="crm-modal-backdrop" wire:click="closeScheduleModal">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head">
            <h3 class="crm-modal__title">Prochaine action</h3>
            @if ($pendingStage)
                <p class="crm-modal__sub">Obligatoire pour déplacer l’opportunité. Après validation, l’étape sera mise à jour.</p>
            @endif
        </div>
        <div class="field"><label class="field-label">Type</label>
            <select class="input" wire:model="scheduleType">
                @foreach ($actionTypes as $k => $lab)<option value="{{ $k }}">{{ $lab }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label class="field-label">Action *</label><input class="input" wire:model="scheduleSummary"></div>
        <div class="field"><label class="field-label">Quand *</label><input class="input" type="datetime-local" wire:model="scheduleDueAt"></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="closeScheduleModal">Annuler</button>
            <button type="button" class="btn btn-primary" wire:click="saveSchedule">Planifier</button>
        </div>
    </div>
</div>
@endif
</div>

