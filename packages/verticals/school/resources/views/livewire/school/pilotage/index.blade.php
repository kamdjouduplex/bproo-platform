<div class="page-body sch-pilotage">
    @include('school::livewire.partials.crud-styles')
    <style>
        .sch-pilotage { display: flex; flex-direction: column; gap: 16px; }
        .sch-pilotage .app-table-card { margin-top: 0; }
        .sch-pilotage-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }
        .sch-pilotage-kpi {
            background: #fff; border: 1px solid #e8eef5; border-radius: 14px; padding: 14px 16px;
        }
        .sch-pilotage-kpi__label {
            margin: 0 0 6px; font-size: 11px; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; color: #94a3b8;
        }
        .sch-pilotage-kpi__value { margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a; }
        .sch-pilotage-kpi__hint { margin: 4px 0 0; font-size: 12px; color: #64748b; }
        .sch-pilotage-kpi--ok .sch-pilotage-kpi__value { color: #047857; }
        .sch-pilotage-kpi--mid .sch-pilotage-kpi__value { color: #b45309; }
        .sch-pilotage-kpi--bad .sch-pilotage-kpi__value { color: #b91c1c; }
        .sch-pilotage-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .sch-pilotage-shortcuts { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 16px; }
        .sch-pilotage-alert {
            display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap;
            padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff;
        }
        .sch-pilotage-alert--warning { border-color: #fde68a; background: #fffbeb; }
        .sch-pilotage-alert--danger { border-color: #fecaca; background: #fef2f2; }
        .sch-pilotage-alert--info { border-color: #bae6fd; background: #f0f9ff; }
        .sch-pilotage-alert strong { display: block; color: #0f172a; }
        .sch-pilotage-alert span { font-size: 13px; color: #64748b; }
        @media (max-width: 900px) { .sch-pilotage-cols { grid-template-columns: 1fr; } }
    </style>

    <section class="card app-table-card">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">Pilotage</h2>
                <p class="sch-modal__hint" style="margin:4px 0 0;">Vue direction : ce qui va, ce qui reste à traiter.</p>
            </div>
            <div class="sch-list-head__actions">
                <select class="input" wire:model.live="yearId" style="max-width:220px;">
                    <option value="">Toutes années</option>
                    @foreach($years as $y)
                        <option value="{{ $y->id }}">{{ $y->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="sch-pilotage-shortcuts">
            @foreach($shortcuts as $item)
                <a class="btn btn-secondary btn-sm" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @endforeach
        </div>
    </section>

    <div class="sch-pilotage-kpis">
        @foreach($kpis as $kpi)
            <article class="sch-pilotage-kpi sch-pilotage-kpi--{{ $kpi['tone'] }}">
                <p class="sch-pilotage-kpi__label">{{ $kpi['label'] }}</p>
                <p class="sch-pilotage-kpi__value">{{ $kpi['value'] }}</p>
                <p class="sch-pilotage-kpi__hint">{{ $kpi['hint'] }}</p>
            </article>
        @endforeach
    </div>

    @if(count($alerts))
        <div style="display:flex; flex-direction:column; gap:8px;">
            @foreach($alerts as $alert)
                <div class="sch-pilotage-alert sch-pilotage-alert--{{ $alert['tone'] }}">
                    <div>
                        <strong>{{ $alert['title'] }}</strong>
                        <span>{{ $alert['hint'] }}</span>
                    </div>
                    @if(!empty($alert['route']))
                        <a class="btn btn-secondary btn-sm" href="{{ $alert['route'] }}">{{ $alert['action'] }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="sch-pilotage-cols">
        <section class="card app-table-card">
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">{{ $effectifs['title'] }}</h2>
            </div>
            <p style="margin:0 16px 10px; color:#64748b; font-size:13px;">{{ $effectifs['summary'] }}</p>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            @foreach($effectifs['headers'] as $h)
                                <th>{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($effectifs['rows'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ max(1, count($effectifs['headers'])) }}">Aucun inscrit pour cette année.</td></tr>
                        @endforelse
                    </tbody>
                    @if(!empty($effectifs['totals']) && count($effectifs['rows']))
                        <tfoot>
                            <tr>
                                @foreach($effectifs['totals'] as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <section class="card app-table-card">
            <div class="sch-list-head">
                <h2 class="sch-list-head__title">{{ $collection['title'] }}</h2>
            </div>
            <p style="margin:0 16px 10px; color:#64748b; font-size:13px;">{{ $collection['summary'] }}</p>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            @foreach($collection['headers'] as $h)
                                <th>{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($collection['rows'] as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ max(1, count($collection['headers'])) }}">Aucun frais imputé pour cette année.</td></tr>
                        @endforelse
                    </tbody>
                    @if(!empty($collection['totals']) && count($collection['rows']))
                        <tfoot>
                            <tr>
                                @foreach($collection['totals'] as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </div>
</div>
