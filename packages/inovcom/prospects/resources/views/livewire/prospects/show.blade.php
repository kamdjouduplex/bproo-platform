<div class="page-body crm-v2">
@if (View::exists('inovcom-crm::partials.styles'))
    @include('inovcom-crm::partials.styles')
@endif
@php
    $opp = $prospect->primaryOpportunity;
    $score = (int) $prospect->score;
    $tempLabel = $prospect->temperatureLabel();
    $acts = $prospect->activities ?? collect();
    $openOpps = $prospect->opportunities ?? collect();
    $actBadge = function ($act) {
        return match ($act->calendarTone()) {
            'rose' => 'rose',
            'orange' => 'orange',
            'green' => 'green',
            'blue' => 'blue',
            default => 'violet',
        };
    };
@endphp
    @if (session('success'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>@endif

    <a href="{{ route('tenant.prospects.index', ['tenant' => $tenantCode]) }}" class="crm-act-row__meta" style="display:inline-block;margin-bottom:8px;">← Retour</a>

    <div class="crm-fiche-hero">
        <div>
            <h2 style="margin:0;font-size:1.45rem;font-weight:800;">Fiche CRM
                <span class="crm-badge crm-badge--{{ $prospect->status === 'qualifie' || $prospect->status === 'converti' ? 'green' : ($prospect->status === 'non_qualifie' ? 'rose' : 'orange') }}">
                    {{ \InovCom\Prospects\Models\Prospect::statusLabel($prospect->status) }}
                </span>
            </h2>
        </div>
        <div class="crm-v2-actions">
            @if ($canUpdate)
                <a class="btn btn-primary" href="{{ route('tenant.prospects.edit', ['tenant' => $tenantCode, 'prospect' => $prospect->id]) }}">Modifier</a>
                @if ($prospect->canBecomeOpportunity())
                    <button type="button" class="btn btn-success" wire:click="openConvertOppModal">Convertir en opportunité</button>
                @endif
            @endif
        </div>
    </div>

    <div class="crm-tabs" role="tablist">
        <button type="button" class="{{ $tab === 'resume' ? 'is-on' : '' }}" wire:click="$set('tab', 'resume')">Résumé</button>
        <button type="button" class="{{ $tab === 'besoin' ? 'is-on' : '' }}" wire:click="$set('tab', 'besoin')">Besoin & Qualification</button>
        <button type="button" class="{{ $tab === 'timeline' ? 'is-on' : '' }}" wire:click="$set('tab', 'timeline')">Timeline</button>
        <button type="button" class="{{ $tab === 'activites' ? 'is-on' : '' }}" wire:click="$set('tab', 'activites')">Activités ({{ $acts->count() }})</button>
        <button type="button" class="{{ $tab === 'opportunites' ? 'is-on' : '' }}" wire:click="$set('tab', 'opportunites')">Opportunités ({{ $openOpps->count() }})</button>
        <button type="button" class="{{ $tab === 'notes' ? 'is-on' : '' }}" wire:click="$set('tab', 'notes')">Notes</button>
    </div>

    <div class="crm-fiche-grid" wire:key="fiche-{{ $tab }}">
        <div>
            @if ($tab === 'resume')
                <section class="crm-card" style="margin-bottom:14px;">
                    <div class="crm-identity">
                        <span class="crm-avatar">{{ $prospect->initials() }}</span>
                        <div>
                            <h3 style="margin:0;font-size:1.2rem;">{{ $prospect->companyDisplayName() !== '—' ? $prospect->companyDisplayName() : $prospect->contactName() }}</h3>
                            <div class="crm-act-row__meta">{{ $prospect->contactName() }}@if($prospect->job_title) · {{ $prospect->job_title }}@endif</div>
                            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                                <span class="crm-badge crm-badge--violet">{{ \InovCom\Prospects\Models\Prospect::typeOptions()[$prospect->type] ?? $prospect->type }}</span>
                                @if ($prospect->sector)<span class="crm-badge crm-badge--slate">{{ $prospect->sector }}</span>@endif
                            </div>
                            <div class="crm-act-row__meta" style="margin-top:8px;">
                                {{ $prospect->phone ?: '—' }}
                                · {{ $prospect->email ?: '—' }}
                                · {{ $prospect->city ?: $prospect->address ?: '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="crm-kv">
                        <div><span>Source</span><strong>{{ \InovCom\Prospects\Models\Prospect::sourceLabel($prospect->source) }}</strong></div>
                        <div><span>Commercial</span><strong>{{ $prospect->owner?->name ?? '—' }}</strong></div>
                        <div><span>Score</span><strong>{{ $score }}/100</strong></div>
                        <div><span>Statut</span><strong>{{ \InovCom\Prospects\Models\Prospect::statusLabel($prospect->status) }}</strong></div>
                    </div>
                </section>
                @if ($prospect->need || $prospect->problem)
                    <section class="crm-card" style="margin-bottom:14px;">
                        <h3 class="crm-card__title">Besoin principal</h3>
                        <p>{{ $prospect->need ?: $prospect->product_interest }}</p>
                        @if ($prospect->problem)
                            <p style="color:#be123c;font-weight:600;">Problème actuel : {{ $prospect->problem }}</p>
                        @endif
                        @if ($prospect->expectations)
                            <p style="color:#047857;">Attentes : {{ $prospect->expectations }}</p>
                        @endif
                    </section>
                @endif
                @if ($opp)
                    <section class="crm-card" style="margin-bottom:14px;">
                        <h3 class="crm-card__title">Opportunité associée
                            <span class="crm-badge crm-badge--{{ $opp->isWon() ? 'green' : ($opp->isLost() ? 'rose' : 'orange') }}">{{ \InovCom\Crm\Models\Opportunity::stageOptions()[$opp->stage] }}</span>
                        </h3>
                        <strong>{{ $opp->title }}</strong>
                        <div class="crm-kv">
                            <div><span>Valeur estimée</span><strong>{{ fmt_money($opp->amount) }} {{ currency_label() }}</strong></div>
                            <div><span>Probabilité</span><strong>{{ (int) $opp->probability }}%</strong></div>
                            <div><span>Étape</span><strong>{{ \InovCom\Crm\Models\Opportunity::stageOptions()[$opp->stage] }}</strong></div>
                            <div><span>Décision</span><strong>{{ $opp->expected_close_date?->format('d/m/Y') ?? '—' }}</strong></div>
                        </div>
                        @if ($opp->canRequestQuote() || $opp->stage === 'intention')
                            <div class="crm-alert crm-alert--blue" style="margin-top:12px;">
                                Intention d’achat détectée. Le CRM ne crée pas le devis.
                                @if ($canUpdate)
                                    <button type="button" class="btn btn-primary btn-sm" style="margin-top:8px;" wire:click="transferToErp">Transmettre au module Devis</button>
                                @endif
                            </div>
                        @endif
                    </section>
                @endif
                <section class="crm-card">
                    <h3 class="crm-card__title">Dernières activités</h3>
                    <div class="crm-tl-v">
                        @forelse ($acts->take(6) as $act)
                            <article class="crm-tl-item is-{{ $act->calendarTone() }}">
                                <span class="crm-tl-dot"></span>
                                <div class="crm-tl-when">{{ ($act->due_at ?? $act->created_at)?->translatedFormat('d M Y · H:i') }}</div>
                                <div class="crm-tl-head">
                                    <span class="crm-badge crm-badge--violet">{{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($act->type) }}</span>
                                    <span class="crm-badge crm-badge--{{ $actBadge($act) }}">{{ $act->calendarLabel() }}</span>
                                </div>
                                <div class="crm-tl-title">{{ $act->displayTitle() }}</div>
                                <div class="crm-tl-meta">{{ $act->assignee?->name ?? $act->user?->name ?? '—' }}@if($act->result) · {{ $act->result }}@endif</div>
                            </article>
                        @empty
                            <p class="crm-empty">Pas encore d’historique.</p>
                        @endforelse
                    </div>
                    @if ($acts->count() > 0)
                        <button type="button" class="btn btn-secondary btn-sm crm-tl-more" wire:click="$set('tab', 'timeline')">Voir toute la timeline</button>
                    @endif
                </section>
            @elseif ($tab === 'besoin')
                <section class="crm-card">
                    <h3 class="crm-card__title">Qualification</h3>
                    <div class="field"><label class="field-label">Besoin identifié</label><input class="input" wire:model="needText"></div>
                    <div class="field"><label class="field-label">Produit / service</label><input class="input" wire:model="productInterest"></div>
                    <div class="field"><label class="field-label">Problème actuel</label><textarea class="input" rows="2" wire:model="problem"></textarea></div>
                    <div class="field"><label class="field-label">Attentes</label><textarea class="input" rows="2" wire:model="expectations"></textarea></div>
                    <div class="field"><label class="field-label">Décideur</label><input class="input" wire:model="decisionMakerName"></div>
                    <div class="prospect-form-grid-2">
                        <div class="field"><label class="field-label">Besoin</label>
                            <select class="input" wire:model="needScore">
                                @foreach (\InovCom\Crm\Services\ProspectScoringService::needOptions() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field"><label class="field-label">Décideur</label>
                            <select class="input" wire:model="decisionScore">
                                @foreach (\InovCom\Crm\Services\ProspectScoringService::decisionOptions() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field"><label class="field-label">Budget</label>
                            <select class="input" wire:model="budgetScore">
                                @foreach (\InovCom\Crm\Services\ProspectScoringService::budgetOptions() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field"><label class="field-label">Échéance</label>
                            <select class="input" wire:model="timelineScore">
                                @foreach (\InovCom\Crm\Services\ProspectScoringService::timelineOptions() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    @if ($canUpdate)
                        <button type="button" class="btn btn-primary" wire:click="saveQualification">Enregistrer la qualification</button>
                    @endif
                </section>
            @elseif ($tab === 'timeline')
                <section class="crm-card">
                    <h3 class="crm-card__title">Timeline</h3>
                    <div class="crm-tl-v">
                        @forelse ($acts as $act)
                            <article class="crm-tl-item is-{{ $act->calendarTone() }}">
                                <span class="crm-tl-dot"></span>
                                <div class="crm-tl-when">{{ ($act->due_at ?? $act->created_at)?->translatedFormat('d M Y · H:i') }}</div>
                                <div class="crm-tl-head">
                                    <span class="crm-badge crm-badge--violet">{{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($act->type) }}</span>
                                    <span class="crm-badge crm-badge--{{ $actBadge($act) }}">{{ $act->calendarLabel() }}</span>
                                </div>
                                <div class="crm-tl-title">{{ $act->displayTitle() }}</div>
                                @if ($act->body)
                                    <div class="crm-tl-body">{{ \Illuminate\Support\Str::limit($act->body, 220) }}</div>
                                @endif
                                <div class="crm-tl-meta">{{ $act->assignee?->name ?? $act->user?->name ?? '—' }}@if($act->result) · Résultat : {{ $act->result }}@endif</div>
                            </article>
                        @empty
                            <p class="crm-empty">Pas encore d’historique.</p>
                        @endforelse
                    </div>
                </section>
            @elseif ($tab === 'activites')
                <section class="crm-card">
                    <h3 class="crm-card__title">Activités</h3>
                    <div class="crm-tl-v">
                        @forelse ($acts as $act)
                            <article class="crm-tl-item is-{{ $act->calendarTone() }}">
                                <span class="crm-tl-dot"></span>
                                <div class="crm-tl-when">{{ ($act->due_at ?? $act->created_at)?->translatedFormat('d M Y · H:i') }}</div>
                                <div class="crm-tl-head">
                                    <span class="crm-badge crm-badge--violet">{{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($act->type) }}</span>
                                    <span class="crm-badge crm-badge--{{ $actBadge($act) }}">{{ $act->calendarLabel() }}</span>
                                </div>
                                <div class="crm-tl-title">{{ $act->displayTitle() }}</div>
                                @if ($act->body)
                                    <div class="crm-tl-body">{{ \Illuminate\Support\Str::limit($act->body, 180) }}</div>
                                @endif
                                <div class="crm-tl-meta">{{ $act->assignee?->name ?? $act->user?->name ?? '—' }}@if($act->result) · Résultat : {{ $act->result }}@endif</div>
                            </article>
                        @empty
                            <p class="crm-empty">Aucune activité pour le moment.</p>
                        @endforelse
                    </div>
                    @if ($canUpdate)
                        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--crm-line);">
                            <h3 class="crm-card__title">Ajouter une activité</h3>
                            <select class="input" wire:model="activityType">
                                @foreach ($activityTypes as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                            <textarea class="input" rows="2" wire:model="activityBody" placeholder="Note, compte-rendu…" style="margin-top:8px;"></textarea>
                            <label style="display:flex;gap:8px;align-items:center;margin:8px 0;cursor:pointer;"><input type="checkbox" wire:model.live="activityIsPlanned"> Planifier une action</label>
                            @if ($activityIsPlanned)
                                <input class="input" type="datetime-local" wire:model="activityDueAt">
                            @endif
                            <button type="button" class="btn btn-primary" style="margin-top:8px;" wire:click="addActivity">Ajouter</button>
                        </div>
                    @endif
                </section>
            @elseif ($tab === 'opportunites')
                <section class="crm-card">
                    @forelse ($openOpps as $item)
                        <div class="crm-act-row">
                            <div>
                                <strong>{{ $item->title }}</strong>
                                <div class="crm-act-row__meta">{{ \InovCom\Crm\Models\Opportunity::stageOptions()[$item->stage] }} · {{ fmt_money($item->amount) }} {{ currency_label() }} · {{ (int) $item->probability }}%</div>
                            </div>
                        </div>
                    @empty
                        <p class="crm-empty">Pas encore d’opportunité.</p>
                    @endforelse
                </section>
            @else
                <section class="crm-card">
                    <p>{{ $prospect->notes ?: 'Aucune note.' }}</p>
                </section>
            @endif
        </div>

        <div class="crm-side-stack">
            <section class="crm-card">
                <h3 class="crm-card__title">Informations clés</h3>
                <div class="crm-source-row"><span>Créé</span><strong>{{ $prospect->created_at?->format('d/m/Y') }}</strong></div>
                <div class="crm-source-row"><span>Dernier contact</span><strong>{{ $prospect->last_contacted_at?->format('d/m/Y') ?? '—' }}</strong></div>
                <div class="crm-source-row"><span>Source</span><strong>{{ \InovCom\Prospects\Models\Prospect::sourceLabel($prospect->source) }}</strong></div>
                <div class="crm-source-row"><span>Intérêt</span><strong>{{ $prospect->product_interest ?: $prospect->need ?: '—' }}</strong></div>
                <div class="crm-source-row"><span>CA estimé</span><strong>{{ $prospect->estimated_budget ? fmt_money($prospect->estimated_budget).' '.currency_label() : '—' }}</strong></div>
                <div class="crm-source-row"><span>Échéance projet</span><strong>{{ $prospect->decision_deadline?->format('d/m/Y') ?? '—' }}</strong></div>
                <div class="crm-source-row"><span>Décideur</span><strong>{{ $prospect->decision_maker_name ?: '—' }}</strong></div>
                <div class="crm-source-row"><span>Commercial</span><strong>{{ $prospect->owner?->name ?? '—' }}</strong></div>
            </section>

            @php $next = $prospect->nextPlannedActivity; @endphp
            <section class="crm-next-card">
                <h3>Prochaine action</h3>
                @if ($next)
                    <strong>{{ $next->displayTitle() }}</strong>
                    <div>{{ $next->due_at?->format('d M Y — H:i') }} · {{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($next->type) }}</div>
                    @if ($canUpdate)
                        <button type="button" class="btn btn-success" style="margin-top:12px;width:100%;" wire:click="completeNextAction({{ $next->id }})">Marquer comme terminée</button>
                    @endif
                @else
                    <p>Aucune action planifiée — c’est une anomalie si une opportunité est ouverte.</p>
                    @if ($canUpdate)
                        <button type="button" class="btn btn-primary" style="margin-top:8px;width:100%;" wire:click="openScheduleModal({{ $prospect->id }})">Planifier une relance</button>
                    @endif
                @endif
            </section>

            <section class="crm-card" style="text-align:center;">
                <h3 class="crm-card__title">Scoring</h3>
                <div class="crm-stat__value" style="font-size:2rem;color:{{ $score >= 60 ? '#16a34a' : ($score >= 30 ? '#ea580c' : '#e11d48') }}">{{ $score }}/100</div>
                <div class="crm-badge crm-badge--{{ $prospect->temperature() === 'chaud' ? 'rose' : ($prospect->temperature() === 'tiede' ? 'orange' : 'slate') }}">Prospect {{ strtolower($tempLabel) }}</div>
            </section>
        </div>
    </div>

@if ($showConvertOppModal)
<div class="crm-modal-backdrop" wire:click="$set('showConvertOppModal', false)">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head"><h3 class="crm-modal__title">Convertir en opportunité</h3><p class="crm-modal__sub">Une opportunité ouverte exige un commercial et une prochaine action.</p></div>
        <div class="field"><label class="field-label">Intitulé *</label><input class="input" wire:model="oppTitle"></div>
        <div class="field"><label class="field-label">Montant estimé</label><input class="input" type="number" min="0" wire:model="oppAmount"></div>
        <div class="field"><label class="field-label">Date de décision</label><input class="input" type="date" wire:model="oppCloseDate"></div>
        <div class="field"><label class="field-label">Prochaine action *</label><input class="input" wire:model="oppNextSummary"></div>
        <div class="field"><label class="field-label">Quand *</label><input class="input" type="datetime-local" wire:model="oppNextDue"></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="$set('showConvertOppModal', false)">Annuler</button>
            <button type="button" class="btn btn-primary" wire:click="convertToOpportunity">Créer l’opportunité</button>
        </div>
    </div>
</div>
@endif

@if ($showScheduleModal)
<div class="crm-modal-backdrop" wire:click="closeScheduleModal">
    <div class="crm-modal" wire:click.stop>
        <div class="crm-modal__head"><h3 class="crm-modal__title">Planifier une action</h3></div>
        <div class="field"><label class="field-label">Type</label>
            <select class="input" wire:model="scheduleType">
                @foreach ($actionTypes as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label class="field-label">Objet</label><input class="input" wire:model="scheduleSummary"></div>
        <div class="field"><label class="field-label">Quand *</label><input class="input" type="datetime-local" wire:model="scheduleDueAt"></div>
        <div class="crm-modal__actions">
            <button type="button" class="btn btn-secondary" wire:click="closeScheduleModal">Annuler</button>
            <button type="button" class="btn btn-primary" wire:click="saveSchedule">Planifier</button>
        </div>
    </div>
</div>
@endif
</div>
