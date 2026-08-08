<div class="page-body crm-page">
    <div class="crm-page__intro">
        <div>
            <h2 class="crm-page__title">Performance commerciale</h2>
            <p class="crm-page__lead">Comparez vos commerciaux : pipeline, conversions, activité et retards.</p>
        </div>
        <div class="crm-page__actions">
            <select class="input input-sm" wire:model.live="period" aria-label="Période">
                <option value="7">7 derniers jours</option>
                <option value="30">30 derniers jours</option>
                <option value="90">90 derniers jours</option>
                <option value="365">12 mois</option>
            </select>
            @if ($canCreate && Route::has('tenant.prospects.create'))
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.prospects.create') }}">Nouveau prospect</a>
            @endif
            @if ($canViewOpp && Route::has('tenant.crm.opportunities'))
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.crm.opportunities') }}">Pipeline</a>
            @endif
        </div>
    </div>

    <section class="crm-kpi-grid">
        <article class="crm-kpi-card">
            <div class="crm-kpi-card__label">Pipeline ouvert</div>
            <div class="crm-kpi-card__value">{{ ($summary['qualifie'] ?? 0) + ($summary['negociation'] ?? 0) + ($summary['gagne'] ?? 0) }}</div>
            <div class="crm-kpi-card__meta">
                @if ($unassignedOpen > 0)
                    dont {{ $unassignedOpen }} non assigné(s)
                @else
                    qualifiés + négociation + gagnés
                @endif
            </div>
        </article>
        <article class="crm-kpi-card crm-kpi-card--blue">
            <div class="crm-kpi-card__label">CA potentiel</div>
            <div class="crm-kpi-card__value">{{ number_format($pipelineValue, 0, ',', ' ') }}</div>
            <div class="crm-kpi-card__meta">valeur estimée ouverte</div>
        </article>
        <article class="crm-kpi-card crm-kpi-card--green">
            <div class="crm-kpi-card__label">Convertis (période)</div>
            <div class="crm-kpi-card__value">{{ $convertedPeriod }}</div>
            <div class="crm-kpi-card__meta">{{ number_format($wonValuePeriod, 0, ',', ' ') }} CA · {{ number_format((float) ($summary['conversion_rate'] ?? 0), 1, ',', ' ') }} % win rate global</div>
        </article>
        <article class="crm-kpi-card crm-kpi-card--teal">
            <div class="crm-kpi-card__label">CA gagné (total)</div>
            <div class="crm-kpi-card__value">{{ number_format($wonValue, 0, ',', ' ') }}</div>
            <div class="crm-kpi-card__meta">{{ $clientsCount }} clients</div>
        </article>
        <article class="crm-kpi-card">
            <div class="crm-kpi-card__label">Actions planifiées</div>
            <div class="crm-kpi-card__value">{{ $plannedOpen }}</div>
            <div class="crm-kpi-card__meta">
                @if ($canViewAct && Route::has('tenant.crm.activities'))
                    <a href="{{ route('tenant.crm.activities') }}">{{ $overdue }} en retard →</a>
                @else
                    {{ $overdue }} en retard
                @endif
            </div>
        </article>
        <article class="crm-kpi-card crm-kpi-card--amber">
            <div class="crm-kpi-card__label">Activités (semaine)</div>
            <div class="crm-kpi-card__value">{{ $activitiesWeek }}</div>
            <div class="crm-kpi-card__meta">journal + actions</div>
        </article>
    </section>

    <section class="crm-panel" style="margin-bottom:14px;">
        <div class="crm-panel__head">
            <h3 class="crm-panel__title">Classement commerciaux</h3>
            <span class="crm-muted">Période : {{ $period }} j</span>
        </div>
        @if (count($repStats) === 0)
            <p class="crm-muted">Assignez des prospects à plusieurs commerciaux pour comparer leurs performances.</p>
        @else
            <div style="overflow-x:auto;">
                <table class="crm-rep-table">
                    <thead>
                        <tr>
                            <th>Commercial</th>
                            <th>Pipeline</th>
                            <th>CA ouvert</th>
                            <th>Convertis</th>
                            <th>CA gagné</th>
                            <th>Perdus</th>
                            <th>Win rate</th>
                            <th>Actions faites</th>
                            <th>Planifiées</th>
                            <th>En retard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($repStats as $rep)
                            <tr>
                                <td>
                                    <strong>{{ $rep['name'] }}</strong>
                                </td>
                                <td>{{ $rep['open_count'] }}</td>
                                <td>{{ number_format($rep['open_value'], 0, ',', ' ') }}</td>
                                <td><strong>{{ $rep['converted'] }}</strong></td>
                                <td class="crm-rep-table__gain">{{ number_format($rep['converted_value'], 0, ',', ' ') }}</td>
                                <td>{{ $rep['lost'] }}</td>
                                <td>
                                    @if ($rep['win_rate'] === null)
                                        —
                                    @else
                                        <span class="{{ $rep['win_rate'] >= 40 ? 'crm-rep-good' : ($rep['win_rate'] < 20 ? 'crm-rep-bad' : '') }}">
                                            {{ number_format($rep['win_rate'], 1, ',', ' ') }} %
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $rep['acts_done'] }}</td>
                                <td>{{ $rep['acts_planned'] }}</td>
                                <td>
                                    @if ($rep['acts_overdue'] > 0)
                                        <span class="crm-tag crm-tag--danger">{{ $rep['acts_overdue'] }}</span>
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="crm-split">
        <section class="crm-panel">
            <div class="crm-panel__head">
                <h3 class="crm-panel__title">Répartition pipeline</h3>
            </div>
            <div class="crm-stage-bars">
                @foreach ([
                    'qualifie' => ['label' => 'Qualifiés', 'tone' => 'blue'],
                    'negociation' => ['label' => 'Négociation', 'tone' => 'violet'],
                    'gagne' => ['label' => 'Gagnés', 'tone' => 'green'],
                    'converti' => ['label' => 'Convertis', 'tone' => 'teal'],
                    'perdu' => ['label' => 'Perdus', 'tone' => 'rose'],
                ] as $key => $meta)
                    @php
                        $n = (int) ($summary[$key] ?? 0);
                        $total = max(1, (int) ($summary['total'] ?? 0));
                        $pct = round(($n / $total) * 100);
                    @endphp
                    <div class="crm-stage-bar">
                        <div class="crm-stage-bar__row">
                            <span>{{ $meta['label'] }}</span>
                            <strong>{{ $n }}</strong>
                        </div>
                        <div class="crm-stage-bar__track">
                            <span class="crm-stage-bar__fill crm-stage-bar__fill--{{ $meta['tone'] }}" style="width: {{ $pct }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="crm-panel">
            <div class="crm-panel__head">
                <h3 class="crm-panel__title">Par source</h3>
            </div>
            @forelse ($bySource as $row)
                <div class="crm-source-row">
                    <div>
                        <strong>{{ $row['label'] }}</strong>
                        <div class="crm-muted">{{ $row['converted'] }} convertis / {{ $row['total'] }}</div>
                    </div>
                    <div class="crm-source-row__rate">{{ number_format($row['rate'], 1, ',', ' ') }} %</div>
                </div>
            @empty
                <p class="crm-muted">Pas encore assez de données.</p>
            @endforelse
        </section>
    </div>

    <section class="crm-panel" style="margin-top:14px;">
        <div class="crm-panel__head">
            <h3 class="crm-panel__title">À traiter</h3>
            @if ($canViewOpp)
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.crm.opportunities') }}">Kanban →</a>
            @endif
        </div>
        <div class="crm-recent-list">
            @forelse ($recent as $p)
                <a class="crm-recent-item" href="{{ route('tenant.prospects.show', $p) }}">
                    <div>
                        <strong>{{ $p->name }}</strong>
                        <div class="crm-muted">
                            {{ $p->reference }} · {{ \InovCom\Prospects\Models\Prospect::statusLabel($p->status) }}
                            @if ($p->nextPlannedActivity)
                                · prochaine : {{ $p->nextPlannedActivity->due_at?->format('d/m H:i') ?? 'planifiée' }}
                            @endif
                        </div>
                    </div>
                    <div class="crm-recent-item__right">
                        @if ($p->expected_value)
                            <strong>{{ number_format((float) $p->expected_value, 0, ',', ' ') }}</strong>
                        @endif
                        <span class="crm-muted">{{ $p->owner?->name ?: 'Non affecté' }}</span>
                    </div>
                </a>
            @empty
                <p class="crm-muted">Aucun prospect ouvert. Créez-en un pour démarrer le pipeline.</p>
            @endforelse
        </div>
    </section>
</div>
