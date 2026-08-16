<div class="dashboard dashboard--school">
    <header class="dashboard-hero">
        <div class="dashboard-hero__text">
            <p class="dashboard-hero__eyebrow">
                {{ $tenantName }}
                @if($activeYear)
                    · {{ $activeYear->name }}
                @endif
                · {{ now()->translatedFormat('l d F Y') }}
            </p>
            <h1 class="dashboard-greeting">Bonjour{{ $userName ? ', '.$userName : '' }}</h1>
            <p class="dashboard-date">
                Vue d’ensemble de la scolarité, des finances et des résultats.
            </p>
        </div>
        @if(count($quickActions) > 0)
            <div class="dashboard-quick-actions">
                @foreach($quickActions as $action)
                    <a href="{{ route($action['route'], ['tenant' => $tenantCode]) }}"
                       class="btn {{ ($action['style'] ?? '') === 'primary' ? 'btn-primary' : 'btn-secondary' }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    <section class="dashboard-kpis dashboard-kpis--pharma">
        <article class="dashboard-kpi dashboard-kpi--tint-blue">
            <div class="dashboard-kpi__icon-row">
                <span class="dashboard-kpi__label">Élèves actifs</span>
            </div>
            <div class="dashboard-kpi__value">{{ number_format($stats['students'], 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">Profils permanents</div>
        </article>

        <article class="dashboard-kpi dashboard-kpi--tint-teal">
            <div class="dashboard-kpi__icon-row">
                <span class="dashboard-kpi__label">Inscriptions</span>
            </div>
            <div class="dashboard-kpi__value">{{ number_format($stats['enrollments'], 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">{{ $activeYear?->name ?? 'Année en cours' }}</div>
        </article>

        <article class="dashboard-kpi dashboard-kpi--tint-green">
            <div class="dashboard-kpi__icon-row">
                <span class="dashboard-kpi__label">Enseignants</span>
            </div>
            <div class="dashboard-kpi__value">{{ number_format($stats['teachers'], 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">Actifs</div>
        </article>

        <article class="dashboard-kpi dashboard-kpi--tint-amber">
            <div class="dashboard-kpi__icon-row">
                <span class="dashboard-kpi__label">Paiements en attente</span>
            </div>
            <div class="dashboard-kpi__value">{{ number_format($stats['pending_payments'], 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">Vérification banque</div>
        </article>

        <article class="dashboard-kpi dashboard-kpi--tint-blue">
            <div class="dashboard-kpi__icon-row">
                <span class="dashboard-kpi__label">Examens ouverts</span>
            </div>
            <div class="dashboard-kpi__value">{{ number_format($stats['open_exams'], 0, ',', ' ') }}</div>
            <div class="dashboard-kpi__meta">Saisie des notes</div>
        </article>

        <article class="dashboard-kpi dashboard-kpi--tint-teal">
            <div class="dashboard-kpi__icon-row">
                <span class="dashboard-kpi__label">Encaissé aujourd’hui</span>
            </div>
            <div class="dashboard-kpi__value">
                {{ number_format($stats['payments_today'], 0, ',', ' ') }}
                <span class="dashboard-kpi__currency">XOF</span>
            </div>
            <div class="dashboard-kpi__meta">Paiements vérifiés</div>
        </article>
    </section>

    @if(count($alerts) > 0)
        <section class="dashboard-alerts" style="margin-top: 8px;">
            @foreach($alerts as $alert)
                <div class="dashboard-alert dashboard-alert--{{ $alert['tone'] }}">
                    <div>
                        <strong>{{ $alert['title'] }}</strong>
                        <div style="font-size: 13px; margin-top: 2px;">{{ $alert['hint'] }}</div>
                    </div>
                    @if(!empty($alert['route']))
                        <a class="dashboard-alert__link" href="{{ route($alert['route'], ['tenant' => $tenantCode]) }}">
                            {{ $alert['action'] ?? 'Ouvrir' }}
                        </a>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if(count($groupedLinks) === 0)
        <section class="card app-table-card" style="margin-top: 16px;">
            <div style="padding: 20px; color:#64748b;">
                Aucun module School n’est activé pour ce tenant. Activez-les depuis le Control Center.
            </div>
        </section>
    @else
        <div class="school-dash-sections">
            @foreach($groupedLinks as $section)
                <section class="dashboard-modules">
                    <h2 class="dashboard-modules__title">{{ $section['label'] }}</h2>
                    <div class="dashboard-modules__grid">
                        @foreach($section['items'] as $item)
                            <a class="dashboard-module-card"
                               href="{{ route($item['route'], ['tenant' => $tenantCode]) }}">
                                <div>
                                    <div class="school-dash-card__label">{{ $item['label'] }}</div>
                                    @if(!empty($item['hint']))
                                        <div class="school-dash-card__hint">{{ $item['hint'] }}</div>
                                    @endif
                                </div>
                                <span class="dashboard-module-card__arrow" aria-hidden="true">→</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <style>
        .dashboard--school {
            --dashboard-accent: #2563eb;
            --dashboard-accent-muted: rgba(37, 99, 235, 0.10);
        }
        .dashboard--school .dashboard-hero {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 50%, rgba(37, 99, 235, 0.08) 100%);
        }
        .school-dash-sections {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
        }
        .dashboard--school .dashboard-modules {
            margin-top: 12px;
            padding: 16px 18px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        .dashboard--school .dashboard-modules__title {
            margin: 0 0 12px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
        }
        .dashboard--school .dashboard-modules__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .dashboard--school .dashboard-module-card {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            text-decoration: none;
            color: inherit;
            transition: border-color .15s ease, background .15s ease, transform .15s ease;
        }
        .dashboard--school .dashboard-module-card:hover {
            border-color: #2563eb;
            background: #fff;
            transform: translateY(-1px);
        }
        .school-dash-card__label {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .school-dash-card__hint {
            font-size: 12px;
            line-height: 1.35;
            color: #64748b;
        }
        .dashboard--school .dashboard-module-card__arrow {
            color: #94a3b8;
            font-weight: 600;
            flex-shrink: 0;
        }
        @media (max-width: 720px) {
            .dashboard--school .dashboard-quick-actions {
                width: 100%;
            }
            .dashboard--school .dashboard-quick-actions .btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</div>
