<div class="page-body sch-reports">
    @include('school::livewire.partials.crud-styles')
    <style>
        .sch-reports { display: flex; flex-direction: column; gap: 16px; }
        .sch-reports .app-table-card { margin-top: 0; }
        .sch-report-groups { display: grid; gap: 14px; padding: 4px 16px 16px; }
        .sch-report-group__label {
            margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; color: #94a3b8;
        }
        .sch-report-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .sch-report-chip {
            border: 1px solid #e2e8f0; background: #fff; color: #334155;
            border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 600;
            cursor: pointer;
        }
        .sch-report-chip:hover { border-color: #3fa796; color: #0f172a; }
        .sch-report-chip.is-active {
            background: #3fa796; border-color: #3fa796; color: #fff;
        }
        .sch-report-kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px; padding: 0 16px 12px;
        }
        .sch-report-kpi {
            border: 1px solid #e8eef5; background: #f8fafc; border-radius: 10px; padding: 10px 12px;
        }
        .sch-report-kpi__label { font-size: 11px; color: #64748b; margin: 0 0 4px; text-transform: uppercase; letter-spacing: .04em; }
        .sch-report-kpi__value { margin: 0; font-size: 1.15rem; font-weight: 700; color: #0f172a; }
        .sch-reports table tfoot td { font-weight: 700; background: #f8fafc; }
    </style>

    <section class="card app-table-card">
        <div class="sch-list-head">
            <div>
                <h2 class="sch-list-head__title">Rapports école</h2>
                <p class="sch-modal__hint" style="margin:4px 0 0;">Choisissez un état, filtrez, puis imprimez ou exportez.</p>
            </div>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="exportCsv">Export CSV</button>
                <a class="btn btn-primary btn-sm" href="{{ $printUrl }}" target="_blank">Imprimer / PDF</a>
            </div>
        </div>

        <div class="sch-report-groups">
            @foreach($groups as $groupLabel => $keys)
                <div>
                    <p class="sch-report-group__label">{{ $groupLabel }}</p>
                    <div class="sch-report-chips">
                        @foreach($keys as $key)
                            <button type="button"
                                    class="sch-report-chip {{ $reportType === $key ? 'is-active' : '' }}"
                                    wire:click="$set('reportType', '{{ $key }}')">
                                {{ $types[$key] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">{{ $report['title'] }}</h2>
        </div>

        <div class="sch-filters" style="flex-wrap:wrap;">
            @if($usesYear)
                <select class="input" wire:model.live="yearId" style="max-width:200px;">
                    <option value="">Toutes années</option>
                    @foreach($years as $y)
                        <option value="{{ $y->id }}">{{ $y->name }}</option>
                    @endforeach
                </select>
            @endif
            @if($usesClass)
                <select class="input" wire:model.live="classId" style="max-width:180px;">
                    <option value="">Toutes classes</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif
            @if($usesTeacher)
                <select class="input" wire:model.live="teacherId" style="max-width:220px;">
                    <option value="">Tous les enseignants</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                    @endforeach
                </select>
            @endif
            @if($usesDates)
                <input class="input" type="date" wire:model.live="dateFrom" style="max-width:160px;">
                <input class="input" type="date" wire:model.live="dateTo" style="max-width:160px;">
            @endif
        </div>

        @if(!empty($report['kpis']))
            <div class="sch-report-kpis">
                @foreach($report['kpis'] as $kpi)
                    <article class="sch-report-kpi">
                        <p class="sch-report-kpi__label">{{ $kpi['label'] }}</p>
                        <p class="sch-report-kpi__value">{{ $kpi['value'] }}</p>
                    </article>
                @endforeach
            </div>
        @endif

        <p style="margin:0 16px 10px; color:#64748b; font-size:13px;">{{ $report['summary'] }}</p>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        @foreach($report['headers'] as $h)
                            <th>{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(1, count($report['headers'])) }}">Aucune ligne pour ces filtres.</td></tr>
                    @endforelse
                </tbody>
                @if(!empty($report['totals']) && count($report['rows']))
                    <tfoot>
                        <tr>
                            @foreach($report['totals'] as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</div>
