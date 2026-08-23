@include('inovcom-crm::partials.styles')
@php
    $k = $data['kpis'];
    $tenantCode = $tenantCode ?? request('tenant');
    $fmtDelta = function (?float $d) {
        if ($d === null) return '';
        $cls = $d >= 0 ? 'is-up' : 'is-down';
        $sign = $d >= 0 ? '+' : '';
        return '<div class="crm-stat__delta '.$cls.'">'.$sign.number_format($d, 1, ',', ' ').'% vs période préc.</div>';
    };
@endphp
<div class="page-body crm-v2">
    @if (session('success'))<div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error" style="margin-bottom:14px;">{{ session('error') }}</div>@endif

    <div class="crm-v2-head">
        <div>
            <h2>Tableau de bord CRM</h2>
            <p>Vue d’ensemble de votre activité commerciale — qui contacter, pourquoi, quelle prochaine action.</p>
        </div>
        <div class="crm-v2-actions">
            <select class="input" wire:model.live="period" aria-label="Période">
                <option value="7">7 derniers jours</option>
                <option value="30">30 derniers jours</option>
                <option value="90">90 derniers jours</option>
            </select>
        </div>
    </div>

    <div class="crm-stat-grid">
        <article class="crm-stat crm-stat--blue">
            <div class="crm-stat__icon">P</div>
            <div class="crm-stat__label">Nouveaux prospects</div>
            <div class="crm-stat__value">{{ $k['new_prospects'] }}</div>
            {!! $fmtDelta($k['new_prospects_delta']) !!}
            <span class="crm-stat__bar"></span>
        </article>
        <article class="crm-stat crm-stat--green">
            <div class="crm-stat__icon">O</div>
            <div class="crm-stat__label">Opportunités ouvertes</div>
            <div class="crm-stat__value">{{ $k['open'] }}</div>
            {!! $fmtDelta($k['open_delta']) !!}
            <span class="crm-stat__bar"></span>
        </article>
        <article class="crm-stat crm-stat--violet">
            <div class="crm-stat__icon">€</div>
            <div class="crm-stat__label">Valeur du pipeline</div>
            <div class="crm-stat__value">{{ fmt_money($k['pipeline_value']) }}</div>
            <div class="crm-stat__delta">{{ currency_label() }}</div>
            <span class="crm-stat__bar"></span>
        </article>
        <article class="crm-stat crm-stat--orange">
            <div class="crm-stat__icon">✓</div>
            <div class="crm-stat__label">Opportunités gagnées</div>
            <div class="crm-stat__value">{{ $k['won'] }}</div>
            {!! $fmtDelta($k['won_delta']) !!}
            <span class="crm-stat__bar"></span>
        </article>
        <article class="crm-stat crm-stat--rose">
            <div class="crm-stat__icon">×</div>
            <div class="crm-stat__label">Opportunités perdues</div>
            <div class="crm-stat__value">{{ $k['lost'] }}</div>
            {!! $fmtDelta($k['lost_delta']) !!}
            <span class="crm-stat__bar"></span>
        </article>
    </div>

    <div class="crm-dash-grid">
        <section class="crm-card">
            <h3 class="crm-card__title">Actions à traiter</h3>
            <div class="crm-action-pills">
                <a class="crm-pill crm-pill--rose" href="{{ route('tenant.crm.activities', ['tenant' => $tenantCode]) }}">En retard <strong>{{ $data['actions']['overdue'] }}</strong></a>
                <a class="crm-pill crm-pill--orange" href="{{ route('tenant.crm.activities', ['tenant' => $tenantCode]) }}">Aujourd’hui <strong>{{ $data['actions']['today'] }}</strong></a>
                <a class="crm-pill crm-pill--green" href="{{ route('tenant.crm.activities', ['tenant' => $tenantCode]) }}">À venir <strong>{{ $data['actions']['upcoming'] }}</strong></a>
            </div>
            <h3 class="crm-card__title" style="margin-top:18px;">Prochaines activités</h3>
            @forelse ($data['next_activities'] as $act)
                <a class="crm-act-row" href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $act->prospect_id]) }}">
                    <div class="crm-act-row__time">{{ $act->due_at?->format('H:i') ?? '—' }}</div>
                    <div>
                        <strong>{{ $act->displayTitle() }}</strong>
                        <div class="crm-act-row__meta">{{ $act->prospect?->companyDisplayName() }} · {{ \InovCom\Prospects\Models\ProspectActivity::typeLabel($act->type) }}</div>
                    </div>
                </a>
            @empty
                <p class="crm-empty">Aucune action planifiée. Créez une prochaine action.</p>
            @endforelse
        </section>

        <section class="crm-card">
            <h3 class="crm-card__title">Pipeline par étape</h3>
            <div class="crm-stage-bars">
                @php $maxAmt = max(1, collect($data['pipeline_by_stage'])->max('amount')); @endphp
                @foreach ($data['pipeline_by_stage'] as $row)
                    <div>
                        <div class="crm-stage-bar__row">
                            <span>{{ $row['label'] }} ({{ $row['count'] }})</span>
                            <span>{{ fmt_money($row['amount']) }} {{ currency_label() }}</span>
                        </div>
                        <div class="crm-stage-bar__track">
                            <span class="crm-stage-bar__fill crm-stage-bar__fill--{{ $row['tone'] === 'violet' ? 'violet' : ($row['tone'] === 'orange' ? 'amber' : ($row['tone'] === 'green' ? 'green' : 'blue')) }}" style="width: {{ round($row['amount'] / $maxAmt * 100) }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
            <h3 class="crm-card__title" style="margin-top:18px;">Opportunités sans activité récente</h3>
            @forelse ($data['stale'] as $opp)
                <a class="crm-act-row" href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $opp->prospect_id]) }}">
                    <div>
                        <strong>{{ $opp->prospect?->companyDisplayName() }}</strong>
                        <div class="crm-act-row__meta">{{ $opp->title }}</div>
                    </div>
                    <span class="crm-badge crm-badge--rose">{{ $opp->last_activity_at ? $opp->last_activity_at->diffInDays(now()).' jours' : 'jamais' }}</span>
                </a>
            @empty
                <p class="crm-empty">Pipeline à jour.</p>
            @endforelse
        </section>

        <section class="crm-card">
            <h3 class="crm-card__title">Répartition par source</h3>
            @forelse ($data['by_source'] as $src)
                <div class="crm-source-row">
                    <span>{{ $src['label'] }}</span>
                    <span class="crm-source-row__rate">{{ $src['share'] }}%</span>
                </div>
            @empty
                <p class="crm-empty">Pas encore de sources.</p>
            @endforelse
            <h3 class="crm-card__title" style="margin-top:16px;">Taux de conversion</h3>
            <div class="crm-stat__value" style="font-size:1.8rem;color:var(--crm-violet)">{{ number_format($k['conversion'], 1, ',', ' ') }}%</div>
            <div class="crm-stat__delta">gagnées / (gagnées + perdues) sur la période</div>
            @if (count($data['activity_mix']) > 0)
                <h3 class="crm-card__title" style="margin-top:16px;">Activité commerciale</h3>
                @foreach ($data['activity_mix'] as $mix)
                    <div class="crm-source-row"><span>{{ $mix['label'] }}</span><strong>{{ $mix['count'] }}</strong></div>
                @endforeach
            @endif
        </section>
    </div>
</div>
