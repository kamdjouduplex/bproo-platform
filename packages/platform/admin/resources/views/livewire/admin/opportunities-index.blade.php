<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Ouvertes</div>
            <div class="dashboard-kpi__value">{{ $kpis['count'] }}</div>
            @if ($kpis['overdue'] > 0)
                <div class="dashboard-kpi__meta" style="color:#b91c1c;">{{ $kpis['overdue'] }} suivi(s) en retard</div>
            @endif
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Pipeline</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($kpis['pipeline']) }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Pondéré</div>
            <div class="dashboard-kpi__value" style="font-size:1.25rem;">{{ fmt_money($kpis['weighted']) }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">À convertir</div>
            <div class="dashboard-kpi__value">{{ $kpis['to_convert'] }}</div>
        </div>
    </section>

    <section class="cc-card" style="margin-bottom:14px;">
        <div class="cc-card__body">
            <div class="form-grid" style="margin:0;align-items:end;">
                <div class="field">
                    <input class="input" type="search" placeholder="Rechercher…" wire:model.live.debounce.300ms="search">
                </div>
                <div class="field">
                    <select class="input" wire:model.live="product">
                        <option value="">Toutes apps</option>
                        @foreach ($productTypes as $key => $cfg)
                            <option value="{{ $key }}">{{ $cfg['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="input" wire:model.live="stage">
                        <option value="">Toutes étapes</option>
                        @foreach ($oppStages as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <select class="input" wire:model.live="owner">
                        <option value="">Tous commerciaux</option>
                        <option value="none">Non affectés</option>
                        @foreach ($salespeople as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-primary" href="{{ route('system.prospects.create', ['stage' => 'qualified']) }}">Nouvelle opportunité</a>
                </div>
            </div>
        </div>
    </section>

    <div class="cc-opp-board cc-opp-board--4">
        @foreach ($oppStages as $stageKey => $stageLabel)
            <section class="cc-opp-col cc-card">
                <div class="cc-opp-col__head">
                    <strong>{{ $stageLabel }}</strong>
                    <span class="badge badge-secondary">{{ $byStage[$stageKey]->count() }}</span>
                </div>
                <div class="cc-opp-col__body">
                    @forelse ($byStage[$stageKey] as $opp)
                        <article class="cc-opp-card">
                            <a href="{{ route('system.prospects.edit', $opp) }}" class="cc-opp-card__title">{{ $opp->company_name }}</a>
                            <div class="cc-opp-card__meta">
                                {{ $opp->productLabel() }}
                                @if ($opp->contact_name)
                                    · {{ $opp->contact_name }}
                                @endif
                            </div>
                            <div class="cc-opp-card__owner">
                                <select class="input input-sm" wire:change="assignOwner({{ $opp->id }}, $event.target.value)" title="Commercial">
                                    <option value="">Commercial…</option>
                                    @foreach ($salespeople as $user)
                                        <option value="{{ $user->id }}" @selected((int) $opp->owner_user_id === (int) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cc-opp-card__value">
                                <strong>{{ $opp->expected_value !== null ? fmt_money($opp->expected_value) : '—' }}</strong>
                                <span>{{ $opp->probability ?? \App\Models\PlatformProspect::defaultProbabilityForStage($opp->stage) }}%</span>
                            </div>
                            <div class="cc-opp-card__follow">
                                @if ($opp->next_follow_up_at)
                                    <span class="{{ $opp->next_follow_up_at->isPast() ? 'cc-opp-card__late' : '' }}">
                                        Suivi {{ $opp->next_follow_up_at->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">Sans suivi</span>
                                @endif
                            </div>
                            <div class="cc-opp-card__actions">
                                @if ($stageKey === 'won')
                                    <a class="btn btn-primary btn-sm" href="{{ route('system.tenants.create', ['prospect' => $opp->id]) }}">Convertir</a>
                                @else
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="advance({{ $opp->id }})">
                                        @if ($stageKey === 'negotiation')
                                            Gagné
                                        @else
                                            Avancer
                                        @endif
                                    </button>
                                @endif
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.prospects.edit', $opp) }}">Fiche</a>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="markLost({{ $opp->id }})" wire:confirm="Marquer perdu ?">Perdu</button>
                            </div>
                        </article>
                    @empty
                        <p class="cc-opp-empty">Vide</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
