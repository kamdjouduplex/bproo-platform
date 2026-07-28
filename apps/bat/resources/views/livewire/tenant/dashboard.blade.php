@php
    $tenantCode = session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $fmt  = fn($n) => number_format((float)$n, 0, ',', ' ');
    $cur  = $tenantCurrency ?? config('inovcom.currency', 'XOF');
    $money = fn($n) => ($n >= 1_000_000
        ? number_format((float)$n / 1_000_000, 2, ',', ' ') . ' M ' . $cur
        : number_format((float)$n, 0, ',', ' ') . ' ' . $cur);
@endphp

<div wire:poll.60000ms.keep-alive>

    {{-- ── Period selector ─────────────────────────────────────────── --}}
    <div class="flex justify-between items-center flex-wrap gap-3 mb-5">
        <div class="text-[15px] text-slate-500">
            {{ __('Bonjour,') }} <strong class="text-slate-900">{{ $user->name }}</strong>
        </div>
        <div class="flex items-center gap-2.5">
            <span class="text-[12px] text-slate-400 font-semibold uppercase tracking-wider">{{ __('Période') }}</span>
            <div class="flex bg-slate-100 rounded-lg p-0.5 gap-0.5">
                @foreach(['7' => '7j', '30' => '30j', '90' => '3M'] as $val => $label)
                    <button wire:click="setPeriod('{{ $val }}')"
                            wire:key="period-{{ $val }}"
                            class="px-3.5 py-1.5 border-none rounded-md text-[13px] font-semibold cursor-pointer transition-all duration-150 {{ $period === $val ? 'bg-white text-slate-900 shadow' : 'bg-transparent text-slate-500 hover:text-slate-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- FINANCE                                                         --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if ($finance)
    <div class="text-[11px] font-bold uppercase tracking-[.08em] text-slate-400 mb-2.5 mt-6">{{ __('Finance') }}</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5 mb-6">

        @php
        $kpis = [
            ['color'=>'blue',  'icon'=>'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z', 'value'=>$money($finance['total_billed']),  'label'=>__('Total facturé'),       'sub'=>$money($finance['period_billed']).' '.__('sur la période')],
            ['color'=>'green', 'icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',                                                         'value'=>$money($finance['total_paid']),   'label'=>__('Total encaissé'),       'sub'=>$fmt($finance['paid_count']).' '.__('facture(s) soldée(s)')],
            ['color'=>($finance['amount_due']>0?'amber':'gray'), 'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',                         'value'=>$money($finance['amount_due']),   'label'=>__('Reste à encaisser'),    'sub'=>$fmt($finance['draft_count']).' '.__('brouillon(s)')],
            ['color'=>($finance['overdue_count']>0?'red':'gray'),'icon'=>'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z','value'=>$fmt($finance['overdue_count']),'label'=>__('Factures en retard'),'sub'=>__('Échéance dépassée'),'link'=>$can['facturation'] ? route('tenant.facturation.index', ['tenant'=>$tenantCode]) : null, 'linkLabel'=>__('Voir').' →'],
        ];
        $colorMap = ['blue'=>['icon_bg'=>'bg-blue-50','icon_text'=>'text-blue-500','border'=>'border-l-blue-500'],'green'=>['icon_bg'=>'bg-emerald-50','icon_text'=>'text-emerald-500','border'=>'border-l-emerald-500'],'amber'=>['icon_bg'=>'bg-amber-50','icon_text'=>'text-amber-500','border'=>'border-l-amber-500'],'red'=>['icon_bg'=>'bg-red-50','icon_text'=>'text-red-500','border'=>'border-l-red-500'],'gray'=>['icon_bg'=>'bg-slate-50','icon_text'=>'text-slate-400','border'=>'border-l-slate-200']];
        @endphp

        @foreach ($kpis as $k)
        @php $c = $colorMap[$k['color']]; @endphp
        <div class="bg-white border border-slate-200 border-l-[3px] {{ $c['border'] }} rounded-xl p-4 sm:p-5 flex gap-3.5 items-start relative transition-shadow hover:shadow-md">
            <div class="w-10 h-10 rounded-[10px] {{ $c['icon_bg'] }} {{ $c['icon_text'] }} flex items-center justify-center shrink-0">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $k['icon'] }}"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xl font-bold text-slate-900 leading-snug mb-0.5">{{ $k['value'] }}</div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $k['label'] }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">{{ $k['sub'] }}</div>
            </div>
            @if (!empty($k['link']))
                <a href="{{ $k['link'] }}" class="absolute bottom-3 right-4 text-[11px] text-indigo-500 font-semibold hover:underline">{{ $k['linkLabel'] }}</a>
            @endif
        </div>
        @endforeach

    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- COMMERCIAL                                                       --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if ($pipeline || $clients)
    <div class="text-[11px] font-bold uppercase tracking-[.08em] text-slate-400 mb-2.5 mt-6">{{ __('Commercial') }}</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 mb-6">

        @if ($pipeline)
        <div class="card rounded-xl p-5">
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm font-bold text-slate-900">{{ __('Pipeline devis') }}</span>
                <a href="{{ route('tenant.devis.index', ['tenant' => $tenantCode]) }}" class="text-xs text-indigo-500 font-semibold hover:underline">{{ __('Voir tous') }}</a>
            </div>

            <div class="flex flex-col gap-2 mb-4">
                @php
                    $steps = [
                        [__('Brouillons'), $pipeline['draft'],    '#94a3b8'],
                        [__('Envoyés'),    $pipeline['sent'],     '#6366f1'],
                        [__('Acceptés'),   $pipeline['accepted'], '#22c55e'],
                        [__('Refusés'),    $pipeline['refused'],  '#ef4444'],
                    ];
                    $maxCount = max(1, $pipeline['total']);
                @endphp
                @foreach ($steps as [$lbl, $cnt, $col])
                <div class="flex items-center gap-2.5">
                    <span class="text-xs text-slate-500 font-semibold w-20 shrink-0">{{ $lbl }}</span>
                    <div class="flex-1 h-2 bg-slate-100 rounded overflow-hidden">
                        <div class="h-full rounded transition-all duration-500"
                             style="width:{{ min(100, $cnt / $maxCount * 100) }}%; background:{{ $col }};"></div>
                    </div>
                    <span class="text-[13px] font-bold text-slate-800 w-7 text-right">{{ $cnt }}</span>
                </div>
                @endforeach
            </div>

            <div class="flex gap-5 flex-wrap pt-4 border-t border-slate-100">
                <div>
                    <div class="text-lg font-bold text-emerald-600">{{ $pipeline['conversion_rate'] }}%</div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">{{ __('Taux conversion') }}</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-slate-900">{{ $money($pipeline['period_value']) }}</div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">{{ __('Signés (période)') }}</div>
                </div>
                @if ($pipeline['expiring_soon'] > 0)
                <div>
                    <div class="text-lg font-bold text-amber-600">{{ $pipeline['expiring_soon'] }}</div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">{{ __('Expirent dans 7j') }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if ($clients)
        <div class="card rounded-xl p-5">
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm font-bold text-slate-900">{{ __('Clients') }}</span>
                <a href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}" class="text-xs text-indigo-500 font-semibold hover:underline">{{ __('Voir tous') }}</a>
            </div>
            <div class="text-center py-5">
                <div class="text-[52px] font-extrabold text-slate-900 leading-none">{{ $fmt($clients['total']) }}</div>
                <div class="text-sm text-slate-400 mt-1">{{ __('clients au total') }}</div>
            </div>
            <div class="flex gap-5 flex-wrap mt-4 pt-4 border-t border-slate-100">
                <div>
                    <div class="text-lg font-bold text-emerald-600">{{ $fmt($clients['active']) }}</div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">{{ __('Actifs') }}</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-blue-600">+{{ $fmt($clients['new_period']) }}</div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">{{ __('Nouveaux (période)') }}</div>
                </div>
            </div>
            <div class="mt-5">
                <a href="{{ route('tenant.clients.create', ['tenant' => $tenantCode]) }}"
                   class="btn btn-secondary w-full justify-center text-xs">
                    + {{ __('Nouveau client') }}
                </a>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- OPÉRATIONS                                                       --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if ($projects || $maintenance)
    <div class="text-[11px] font-bold uppercase tracking-[.08em] text-slate-400 mb-2.5 mt-6">{{ __('Opérations') }}</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 mb-6">

        @if ($projects)
        <div class="card rounded-xl p-5">
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm font-bold text-slate-900">{{ __('Projets') }}</span>
                <a href="{{ route('tenant.projets.index', ['tenant' => $tenantCode]) }}" class="text-xs text-indigo-500 font-semibold hover:underline">{{ __('Voir tous') }}</a>
            </div>

            <div class="flex gap-2 flex-wrap mb-4">
                @foreach([
                    [$projects['planned'],     __('Planifiés'),  'bg-slate-50 border-slate-200 text-slate-500'],
                    [$projects['in_progress'], __('En cours'),   'bg-blue-50 border-blue-200 text-blue-600'],
                    [$projects['on_hold'],     __('En attente'), 'bg-amber-50 border-amber-200 text-amber-600'],
                    [$projects['completed'],   __('Terminés'),   'bg-emerald-50 border-emerald-200 text-emerald-600'],
                ] as [$cnt, $lbl, $cls])
                <div class="flex-1 min-w-[60px] rounded-xl p-2.5 text-center border {{ $cls }}">
                    <span class="block text-[22px] font-extrabold leading-none">{{ $cnt }}</span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-current opacity-70 mt-1">{{ $lbl }}</span>
                </div>
                @endforeach
            </div>

            @if ($projects['total_budget'] > 0)
            @php $pct = min(100, round($projects['total_cost'] / $projects['total_budget'] * 100)); @endphp
            <div class="mt-4">
                <div class="flex justify-between text-xs text-slate-500 mb-1.5">
                    <span>{{ __('Budget vs Coût réel') }}</span>
                    <span class="{{ $pct > 100 ? 'text-red-500' : 'text-emerald-600' }} font-semibold">{{ $pct }}%</span>
                </div>
                <div class="h-2 bg-slate-100 rounded overflow-hidden">
                    <div class="h-full rounded transition-all duration-500 {{ $pct > 100 ? 'bg-red-500' : 'bg-indigo-500' }}"
                         style="width:{{ min(100, $pct) }}%"></div>
                </div>
                <div class="flex justify-between text-[11px] text-slate-400 mt-1">
                    <span>{{ $money($projects['total_cost']) }}</span>
                    <span>{{ $money($projects['total_budget']) }}</span>
                </div>
            </div>
            @endif

            @if ($projects['late'] > 0)
            <div class="flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 rounded-lg px-3 py-2 mt-3">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                {{ $projects['late'] }} {{ __('projet(s) en retard sur délai') }}
            </div>
            @endif

            @if ($projects['recent']->count())
            <ul class="list-none m-0 p-0 flex flex-col gap-1.5 mt-3">
                @foreach ($projects['recent'] as $p)
                <li class="flex items-center gap-2 text-[13px]">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $p->status === 'in_progress' ? 'bg-blue-500' : ($p->status === 'on_hold' ? 'bg-amber-500' : 'bg-slate-300') }}"></span>
                    <a href="{{ route('tenant.projets.edit', ['tenant' => $tenantCode, 'project' => $p->id]) }}"
                       class="flex-1 text-slate-600 hover:text-indigo-500 truncate overflow-hidden whitespace-nowrap">
                        {{ $p->code }} — {{ \Illuminate\Support\Str::limit($p->title, 35) }}
                    </a>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif

        @if ($maintenance)
        <div class="card rounded-xl p-5">
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm font-bold text-slate-900">{{ __('Ordres de maintenance') }}</span>
                <a href="{{ route('tenant.maintenance.orders.index', ['tenant' => $tenantCode]) }}" class="text-xs text-indigo-500 font-semibold hover:underline">{{ __('Voir tous') }}</a>
            </div>

            <div class="flex gap-2 flex-wrap mb-4">
                @foreach([
                    [$maintenance['open'],        __('Ouverts'),  'bg-slate-50 border-slate-200 text-slate-500'],
                    [$maintenance['assigned'],     __('Assignés'), 'bg-blue-50 border-blue-200 text-blue-600'],
                    [$maintenance['in_progress'],  __('En cours'), 'bg-indigo-50 border-indigo-200 text-indigo-600'],
                    [$maintenance['done'],         __('Terminés'), 'bg-emerald-50 border-emerald-200 text-emerald-600'],
                ] as [$cnt, $lbl, $cls])
                <div class="flex-1 min-w-[60px] rounded-xl p-2.5 text-center border {{ $cls }}">
                    <span class="block text-[22px] font-extrabold leading-none">{{ $cnt }}</span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-current opacity-70 mt-1">{{ $lbl }}</span>
                </div>
                @endforeach
            </div>

            @if ($maintenance['sla_breached'] > 0 || $maintenance['critical'] > 0)
            <div class="flex gap-2 mt-3 flex-wrap">
                @if ($maintenance['sla_breached'] > 0)
                <div class="flex-1 flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 rounded-lg px-3 py-2">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    {{ $maintenance['sla_breached'] }} {{ __('SLA dépassé(s)') }}
                </div>
                @endif
                @if ($maintenance['critical'] > 0)
                <div class="flex-1 flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 rounded-lg px-3 py-2">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    {{ $maintenance['critical'] }} {{ __('ordre(s) critique(s)') }}
                </div>
                @endif
            </div>
            @endif

            @if ($maintenance['recent']->count())
            <ul class="list-none m-0 p-0 flex flex-col gap-1.5 mt-3">
                @foreach ($maintenance['recent'] as $o)
                <li class="flex items-center gap-2 text-[13px]">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $o->priority === 'critical' ? 'bg-red-500' : ($o->priority === 'high' ? 'bg-amber-500' : 'bg-blue-400') }}"></span>
                    <a href="{{ route('tenant.maintenance.orders.edit', ['tenant' => $tenantCode, 'maintenance_order' => $o->id]) }}"
                       class="flex-1 text-slate-600 hover:text-indigo-500 truncate overflow-hidden whitespace-nowrap">
                        {{ $o->code }} — {{ \Illuminate\Support\Str::limit($o->title, 35) }}
                    </a>
                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $o->priority === 'critical' ? 'bg-red-100 text-red-600' : ($o->priority === 'high' ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-400') }} shrink-0">{{ $o->priority }}</span>
                </li>
                @endforeach
            </ul>
            @endif

            <div class="mt-4">
                <a href="{{ route('tenant.maintenance.orders.create', ['tenant' => $tenantCode]) }}"
                   class="btn btn-secondary w-full justify-center text-xs">
                    + {{ __('Nouvel ordre') }}
                </a>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ACHATS                                                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if ($achats)
    <div class="text-[11px] font-bold uppercase tracking-[.08em] text-slate-400 mb-2.5 mt-6">{{ __('Achats') }}</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5 mb-6">
        @foreach([
            ['gray',  $fmt($achats['draft']),         __('Brouillons'),               null],
            ['amber', $fmt($achats['pending']),        __('En validation'),            null],
            ['green', $fmt($achats['validated']),      __('Validés / Commandés'),      null],
            ['blue',  $money($achats['period_total']), __('Total commandé (période)'), route('tenant.achats.index', ['tenant' => $tenantCode])],
        ] as [$color, $val, $lbl, $link])
        @php
            $bc = ['gray'=>'border-l-slate-300','amber'=>'border-l-amber-400','green'=>'border-l-emerald-500','blue'=>'border-l-blue-500'][$color];
        @endphp
        <div class="bg-white border border-slate-200 border-l-[3px] {{ $bc }} rounded-xl p-4 relative transition-shadow hover:shadow-md">
            <div class="text-xl font-bold text-slate-900 mb-1">{{ $val }}</div>
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $lbl }}</div>
            @if (!empty($link))
                <a href="{{ $link }}" class="absolute bottom-3 right-4 text-[11px] text-indigo-500 font-semibold hover:underline">{{ __('Voir') }} →</a>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Empty state                                                       --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if (!array_filter($can))
    <div class="card rounded-xl text-center py-16 px-6">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#cbd5e1" class="w-12 h-12 mx-auto mb-4">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <p class="text-slate-500 text-[15px]">{{ __('Bienvenue. Contactez votre administrateur pour obtenir des accès.') }}</p>
    </div>
    @endif

</div>
