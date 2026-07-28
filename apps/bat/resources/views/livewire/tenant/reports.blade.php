@php
    $tenantCode = session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $cur   = $tenantCurrency ?? config('inovcom.currency', 'XOF');
    $money = fn($n) => number_format((float)$n, 0, ',', ' ') . ' ' . $cur;

    // Reusable class builders
    $stat = fn(string $accent) => 'flex-1 min-w-[140px] bg-white border border-slate-200 rounded-xl px-4 py-4 border-l-[3px] ' . match($accent) {
        'red'    => 'border-l-red-500',
        'amber'  => 'border-l-amber-500',
        'orange' => 'border-l-orange-500',
        'blue'   => 'border-l-indigo-500',
        'green'  => 'border-l-emerald-500',
        default  => 'border-l-slate-300',
    };
    $projectBadge = fn(string $status) => 'inline-block px-2 py-0.5 rounded text-[11px] font-bold ' . match($status) {
        'planned'     => 'bg-blue-50 text-blue-500',
        'in_progress' => 'bg-emerald-50 text-emerald-600',
        'on_hold'     => 'bg-amber-50 text-amber-600',
        'completed'   => 'bg-emerald-50 text-emerald-700',
        'closed'      => 'bg-slate-100 text-slate-500',
        default       => 'bg-slate-100 text-slate-500',
    };
    $projectLabel = fn(string $status) => match($status) {
        'planned'     => __('Planifié'),
        'in_progress' => __('En cours'),
        'on_hold'     => __('En attente'),
        'completed'   => __('Terminé'),
        'closed'      => __('Clôturé'),
        default       => $status,
    };
@endphp

<div>

    {{-- ── Tab bar ────────────────────────────────────────────────── --}}
    <div class="flex gap-0 border-b-2 border-slate-200 mb-6">
        @foreach([
            ['key' => 'aging',       'label' => __('Vieillissement AR')],
            ['key' => 'revenue',     'label' => __('Revenus')],
            ['key' => 'quotes',      'label' => __('Pipeline devis')],
            ['key' => 'rentabilite', 'label' => __('Rentabilité projets')],
            ['key' => 'technicien',  'label' => __('Rapport techniciens')],
        ] as $t)
        <button wire:click="$set('tab','{{ $t['key'] }}')"
                class="px-5 py-2.5 text-[13px] font-bold border-b-2 cursor-pointer bg-transparent border-none relative bottom-[-2px] transition-colors
                       {{ $tab === $t['key'] ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-900' }}">
            {{ $t['label'] }}
        </button>
        @endforeach
    </div>

    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1 — AR AGING                                            --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'aging')

    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <span class="text-[13px] text-slate-500 font-semibold">
            {{ $aging->count() }} {{ __('client(s) avec solde impayé') }}
        </span>
        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border-[1.5px] border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 cursor-pointer hover:border-indigo-500 hover:text-indigo-600 transition-colors"
                wire:click="exportAging">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Exporter CSV') }}
        </button>
    </div>

    @if($agingTotals)
    <div class="flex gap-3 flex-wrap mb-5">
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($agingTotals['total']) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Total impayé') }}</div>
        </div>
        <div class="{{ $stat('green') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($agingTotals['b0_30']) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Courant (0–30j)') }}</div>
        </div>
        <div class="{{ $stat('amber') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($agingTotals['b31_60']) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('31 – 60 jours') }}</div>
        </div>
        <div class="{{ $stat('orange') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($agingTotals['b61_90']) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('61 – 90 jours') }}</div>
        </div>
        <div class="{{ $stat('red') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($agingTotals['b90plus']) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('> 90 jours') }}</div>
        </div>
    </div>
    @endif

    @if($aging->count())
    <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('Client') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('Total dû') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('0 – 30j') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('31 – 60j') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('61 – 90j') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('> 90j') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">{{ __('% > 60j') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($aging as $row)
                @php $pct60 = $row->total_due > 0 ? round(($row->bucket_61_90 + $row->bucket_90plus) / $row->total_due * 100) : 0; @endphp
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $row->client_name }}</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-900 tabular-nums">{{ $money($row->total_due) }}</td>
                    <td class="px-4 py-3 text-right text-emerald-600 tabular-nums">{{ $money($row->bucket_0_30) }}</td>
                    <td class="px-4 py-3 text-right text-amber-600 tabular-nums">{{ $money($row->bucket_31_60) }}</td>
                    <td class="px-4 py-3 text-right text-orange-600 tabular-nums">{{ $money($row->bucket_61_90) }}</td>
                    <td class="px-4 py-3 text-right text-red-600 font-bold tabular-nums">{{ $money($row->bucket_90plus) }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($pct60 > 0)
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold {{ $pct60 > 50 ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600' }}">
                                {{ $pct60 }}%
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            @if($agingTotals)
            <tfoot>
                <tr class="bg-slate-50 border-t-2 border-slate-200">
                    <td class="px-4 py-3 font-bold text-[13px]">{{ __('TOTAL') }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($agingTotals['total']) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($agingTotals['b0_30']) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($agingTotals['b31_60']) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($agingTotals['b61_90']) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($agingTotals['b90plus']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @else
        <div class="card text-center py-16 text-slate-400 text-[14px]">{{ __('Aucune facture impayée. Excellent !') }} ✓</div>
    @endif

    @endif

    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2 — REVENUE                                             --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'revenue')

    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <select class="input input-sm" wire:model.live="period">
            <option value="6">{{ __('6 derniers mois') }}</option>
            <option value="12">{{ __('12 derniers mois') }}</option>
            <option value="24">{{ __('24 derniers mois') }}</option>
        </select>
        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border-[1.5px] border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 cursor-pointer hover:border-indigo-500 hover:text-indigo-600 transition-colors"
                wire:click="exportRevenue">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Exporter CSV') }}
        </button>
    </div>

    @php
        $totalBilled    = $revenue->sum('billed');
        $totalCollected = $revenue->sum('collected');
        $totalInvoices  = $revenue->sum('count');
        $collectRate    = $totalBilled > 0 ? round($totalCollected / $totalBilled * 100) : 0;
    @endphp
    <div class="flex gap-3 flex-wrap mb-5">
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($totalBilled) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Total facturé') }}</div>
        </div>
        <div class="{{ $stat('green') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($totalCollected) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Total encaissé') }}</div>
        </div>
        <div class="{{ $stat('amber') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $collectRate }}%</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Taux encaissement') }}</div>
        </div>
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ number_format($totalInvoices, 0, ',', ' ') }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Factures émises') }}</div>
        </div>
    </div>

    @if($revenue->count())
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="text-[13px] font-bold text-slate-900 mb-5">{{ __('Facturé vs Encaissé par mois') }}</div>
        <div class="flex items-end gap-2 h-[200px] pb-8 relative">
            @foreach($revenue as $m)
            @php
                $billedH    = $maxBilled > 0 ? round($m->billed    / $maxBilled * 180) : 0;
                $collectedH = $maxBilled > 0 ? round($m->collected / $maxBilled * 180) : 0;
            @endphp
            <div class="flex-1 flex flex-col items-center gap-0.5 h-full justify-end">
                <div class="w-full flex gap-0.5 items-end" style="height:{{ max($billedH, $collectedH, 4) }}px;">
                    <div class="flex-1 rounded-t min-h-[2px] bg-indigo-500/85" style="height:{{ $billedH }}px;" title="{{ $money($m->billed) }}"></div>
                    <div class="flex-1 rounded-t min-h-[2px] bg-emerald-500" style="height:{{ $collectedH }}px;" title="{{ $money($m->collected) }}"></div>
                </div>
                <span class="text-[9px] text-slate-400 mt-1.5 whitespace-nowrap">{{ $m->month_label }}</span>
            </div>
            @endforeach
        </div>
        <div class="flex gap-4 mt-2">
            <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-semibold">
                <div class="w-2.5 h-2.5 rounded-sm bg-indigo-500"></div>{{ __('Facturé') }}
            </div>
            <div class="flex items-center gap-1.5 text-[12px] text-slate-500 font-semibold">
                <div class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></div>{{ __('Encaissé') }}
            </div>
        </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl mt-5">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Mois') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Factures') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Facturé') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Encaissé') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Taux') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($revenue->sortByDesc('month_key') as $m)
                @php $rate = $m->billed > 0 ? round($m->collected / $m->billed * 100) : 0; @endphp
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $m->month_label }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $m->count }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $money($m->billed) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-600">{{ $money($m->collected) }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-16 h-1.5 bg-slate-100 rounded overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded" style="width:{{ $rate }}%;"></div>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 w-8 text-right">{{ $rate }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div class="card text-center py-16 text-slate-400 text-[14px]">{{ __('Aucune facture sur la période sélectionnée.') }}</div>
    @endif

    @endif

    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- TAB 3 — QUOTES PIPELINE                                     --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'quotes')

    @php $q = $quotes; @endphp
    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <select class="input input-sm" wire:model.live="year">
            @foreach(range(now()->year, now()->year - 3) as $y)
                <option value="{{ $y }}">{{ $y }}</option>
            @endforeach
        </select>
        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border-[1.5px] border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 cursor-pointer hover:border-indigo-500 hover:text-indigo-600 transition-colors"
                wire:click="exportQuotes">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Exporter CSV') }}
        </button>
    </div>

    <div class="flex gap-3 flex-wrap mb-5">
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $q['total'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Total devis') }}</div>
        </div>
        <div class="{{ $stat('green') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $q['accepted'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Acceptés') }}</div>
        </div>
        <div class="{{ $stat('amber') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ ($q['total'] ?? 0) > 0 ? round(($q['accepted'] ?? 0) / $q['total'] * 100) : 0 }}%</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Taux conversion') }}</div>
        </div>
        <div class="{{ $stat('green') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($q['valueAccepted'] ?? 0) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Valeur signée') }}</div>
        </div>
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($q['valueSent'] ?? 0) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('En attente de réponse') }}</div>
        </div>
    </div>

    {{-- Funnel --}}
    <div class="card px-6 py-5 mb-5">
        <div class="text-[13px] font-bold text-slate-900 mb-5">{{ __('Entonnoir de conversion') }}</div>
        @php $total = max(1, $q['total'] ?? 1); @endphp
        <div class="flex flex-col gap-2.5">
            @foreach([
                ['label' => __('Brouillons'), 'count' => $q['draft']    ?? 0, 'color' => '#94a3b8'],
                ['label' => __('Envoyés'),    'count' => $q['sent']     ?? 0, 'color' => '#6366f1'],
                ['label' => __('Acceptés'),   'count' => $q['accepted'] ?? 0, 'color' => '#22c55e'],
                ['label' => __('Refusés'),    'count' => $q['refused']  ?? 0, 'color' => '#ef4444'],
            ] as $step)
            <div class="flex items-center gap-3">
                <span class="w-[90px] text-[12px] font-bold text-slate-600 flex-shrink-0">{{ $step['label'] }}</span>
                <div class="flex-1 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-[width] duration-500" style="width:{{ min(100, $step['count'] / $total * 100) }}%;background:{{ $step['color'] }};"></div>
                </div>
                <span class="text-[13px] font-bold text-slate-900 w-9 text-right">{{ $step['count'] }}</span>
                <span class="text-[11px] text-slate-400 w-[60px] text-right">{{ min(100, round($step['count'] / $total * 100)) }}%</span>
            </div>
            @endforeach
        </div>
    </div>

    @if(!empty($q['monthly']) && $q['monthly']->count())
    <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Mois (acceptés)') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Nbre') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Valeur TTC') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($q['monthly'] as $m)
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $m->month_label }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $m->count }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-600">{{ $money($m->value) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div class="card text-center py-16 text-slate-400 text-[14px]">{{ __('Aucun devis accepté sur cette année.') }}</div>
    @endif

    @endif

    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- TAB 4 — RENTABILITÉ PROJETS                                  --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'rentabilite')

    @php
        $totalBudget  = $rentabilite->sum('budget');
        $totalCost    = $rentabilite->sum('actual_cost');
        $totalBilled  = $rentabilite->sum('billed');
        $totalCollect = $rentabilite->sum('collected');
        $totalMargin  = $rentabilite->sum('margin');
        $globalMgPct  = $totalCollect > 0 ? round($totalMargin / $totalCollect * 100, 1) : null;
    @endphp

    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <span class="text-[13px] text-slate-500 font-semibold">
            {{ $rentabilite->count() }} {{ __('projet(s) actif(s) / terminé(s)') }}
        </span>
        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border-[1.5px] border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 cursor-pointer hover:border-indigo-500 hover:text-indigo-600 transition-colors"
                wire:click="exportRentabilite">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Exporter CSV') }}
        </button>
    </div>

    <div class="flex gap-3 flex-wrap mb-5">
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($totalBudget) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Budget total') }}</div>
        </div>
        <div class="{{ $stat('orange') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($totalCost) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Coût réel total') }}</div>
        </div>
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($totalBilled) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Total facturé') }}</div>
        </div>
        <div class="{{ $stat('green') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $money($totalCollect) }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Total encaissé') }}</div>
        </div>
        <div class="{{ $stat($totalMargin >= 0 ? 'green' : 'red') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">
                {{ $money($totalMargin) }}
                @if($globalMgPct !== null)
                    <span class="text-[12px] font-semibold opacity-70">({{ $globalMgPct }}%)</span>
                @endif
            </div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Marge globale') }}</div>
        </div>
    </div>

    @if($rentabilite->count())
    <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Projet') }}</th>
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Client') }}</th>
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Statut') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Avancement') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Budget') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Coût réel') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Encaissé') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Marge') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Marge %') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rentabilite as $p)
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-semibold text-slate-700">{{ $p->code }}</div>
                        <div class="text-[11px] text-slate-400">{{ $p->title }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $p->client_name }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ $projectBadge($p->status) }}">{{ $projectLabel($p->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <div class="w-12 h-1.5 bg-slate-100 rounded overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded" style="width:{{ $p->progress }}%;"></div>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 w-7 text-right">{{ $p->progress }}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums {{ $p->over_budget ? 'text-red-600 font-bold' : 'text-slate-700' }}">
                        @if($p->budget > 0)
                            {{ $money($p->budget) }}
                            @if($p->over_budget)
                                <div class="text-[10px] text-red-600">⚠ {{ __('Dépassé') }}</div>
                            @endif
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $money($p->actual_cost) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-600">{{ $money($p->collected) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold {{ $p->margin >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $money($p->margin) }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($p->margin_pct !== null)
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold {{ $p->margin_pct >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">
                                {{ $p->margin_pct }}%
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 border-t-2 border-slate-200">
                    <td colspan="4" class="px-4 py-3 font-bold text-[13px]">{{ __('TOTAL') }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($totalBudget) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($totalCost) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $money($totalCollect) }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums {{ $totalMargin >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $money($totalMargin) }}</td>
                    <td class="px-4 py-3 text-right font-bold">{{ $globalMgPct !== null ? $globalMgPct . '%' : '—' }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
        <div class="card text-center py-16 text-slate-400 text-[14px]">{{ __('Aucun projet actif ou terminé.') }}</div>
    @endif

    @endif

    {{-- ════════════════════════════════════════════════════════════ --}}
    {{-- TAB 5 — RAPPORT TECHNICIENS                                  --}}
    {{-- ════════════════════════════════════════════════════════════ --}}
    @if($tab === 'technicien')

    @php
        $totalInterventions = $technicien->sum('total');
        $totalDone          = $technicien->sum('done');
        $totalHours         = $technicien->sum('total_minutes') > 0
            ? round($technicien->sum('total_minutes') / 60, 1) : 0;
        $totalBreached      = $technicien->sum('sla_breached');
        $globalSlaRate      = $totalInterventions > 0
            ? round($totalBreached / $totalInterventions * 100) : 0;
        $slaAccent          = $globalSlaRate > 20 ? 'red' : ($globalSlaRate > 0 ? 'amber' : 'green');
    @endphp

    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <select class="input input-sm" wire:model.live="year">
            @foreach(range(now()->year, now()->year - 3) as $y)
                <option value="{{ $y }}">{{ $y }}</option>
            @endforeach
        </select>
        <button class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border-[1.5px] border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 cursor-pointer hover:border-indigo-500 hover:text-indigo-600 transition-colors"
                wire:click="exportTechnicien">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Exporter CSV') }}
        </button>
    </div>

    <div class="flex gap-3 flex-wrap mb-5">
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $technicien->count() }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Techniciens actifs') }}</div>
        </div>
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ number_format($totalInterventions, 0, ',', ' ') }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Interventions') }}</div>
        </div>
        <div class="{{ $stat('green') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ number_format($totalDone, 0, ',', ' ') }}</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Réalisées') }}</div>
        </div>
        <div class="{{ $stat('blue') }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $totalHours }}h</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Heures terrain') }}</div>
        </div>
        <div class="{{ $stat($slaAccent) }}">
            <div class="text-[18px] font-extrabold text-slate-900 leading-none">{{ $globalSlaRate }}%</div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mt-1">{{ __('Taux hors SLA') }}</div>
        </div>
    </div>

    @if($technicien->count())
    <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Technicien') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Total') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Réalisées') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Taux réal.') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Durée totale') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Durée moy.') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Hors SLA') }}</th>
                    <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Taux SLA') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($technicien as $t)
                @php
                    $realizRate = $t->total > 0 ? round($t->done / $t->total * 100) : 0;
                    $hours      = $t->total_minutes > 0 ? round($t->total_minutes / 60, 1) : 0;
                @endphp
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $t->technician_name }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $t->total }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $t->done }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-16 h-1.5 bg-slate-100 rounded overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded" style="width:{{ $realizRate }}%;"></div>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 w-8 text-right">{{ $realizRate }}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $hours > 0 ? $hours . 'h' : '—' }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">
                        @if($t->avg_minutes !== null)
                            {{ $t->avg_minutes >= 60
                                ? floor($t->avg_minutes / 60) . 'h' . str_pad($t->avg_minutes % 60, 2, '0', STR_PAD_LEFT)
                                : $t->avg_minutes . ' min' }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums font-bold {{ $t->sla_breached > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        {{ $t->sla_breached }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold {{ $t->sla_rate > 20 ? 'bg-red-50 text-red-600' : ($t->sla_rate > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600') }}">
                            {{ $t->sla_rate }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 border-t-2 border-slate-200">
                    <td class="px-4 py-3 font-bold text-[13px]">{{ __('TOTAL') }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $totalInterventions }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $totalDone }}</td>
                    <td></td>
                    <td class="px-4 py-3 text-right font-bold">{{ $totalHours > 0 ? $totalHours . 'h' : '—' }}</td>
                    <td></td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums">{{ $totalBreached }}</td>
                    <td class="px-4 py-3 text-right font-bold">{{ $globalSlaRate }}%</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
        <div class="card text-center py-16 text-slate-400 text-[14px]">{{ __('Aucune intervention enregistrée pour cette année.') }}</div>
    @endif

    @endif

</div>
