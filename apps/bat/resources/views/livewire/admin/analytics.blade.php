<div>
    {{-- KPI row --}}
    <div class="flex flex-wrap gap-3.5 mb-6">
        @foreach([
            ['blue',  $totalTenants,                   __('Entreprises totales')],
            ['green', $activeTenants,                  __('Actives')],
            ['green', $provisionedTenants,             __('Provisionnées')],
            ['amber', $totalTenants - $activeTenants,  __('Inactives')],
        ] as [$color, $val, $lbl])
        @php $bc = ['blue'=>'border-l-indigo-500','green'=>'border-l-emerald-500','amber'=>'border-l-amber-500','red'=>'border-l-red-500'][$color]; @endphp
        <div class="flex-1 min-w-[150px] bg-white border border-slate-200 border-l-[3px] {{ $bc }} rounded-xl px-5 py-4">
            <div class="text-[28px] font-extrabold text-slate-900 leading-none">{{ $val }}</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mt-1.5">{{ $lbl }}</div>
        </div>
        @endforeach
        @if ($failedTenants > 0)
        <div class="flex-1 min-w-[150px] bg-white border border-slate-200 border-l-[3px] border-l-red-500 rounded-xl px-5 py-4">
            <div class="text-[28px] font-extrabold text-red-600 leading-none">{{ $failedTenants }}</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mt-1.5">{{ __('Échecs provisionnement') }}</div>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">

        {{-- Plan distribution --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="text-[13px] font-bold text-slate-900 mb-4">{{ __('Répartition abonnements') }}</div>
            @foreach([
                ['free',       'Gratuit',    '#94a3b8'],
                ['starter',    'Starter',    '#3b82f6'],
                ['pro',        'Pro',        '#7c3aed'],
                ['enterprise', 'Enterprise', '#a21caf'],
            ] as [$key, $label, $color])
            @php
                $count = $planDistribution->get($key)?->count ?? 0;
                $pct   = $totalTenants > 0 ? round($count / $totalTenants * 100) : 0;
            @endphp
            <div class="flex items-center gap-2.5 mb-3 last:mb-0">
                <span class="text-xs font-bold text-slate-500 w-20 shrink-0">{{ $label }}</span>
                <div class="flex-1 h-2.5 bg-slate-100 rounded overflow-hidden">
                    <div class="h-full rounded transition-all duration-500" style="width:{{ $pct }}%;background:{{ $color }};"></div>
                </div>
                <span class="text-[13px] font-extrabold text-slate-800 w-7 text-right">{{ $count }}</span>
            </div>
            @endforeach
        </div>

        {{-- Module adoption --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="text-[13px] font-bold text-slate-900 mb-4">{{ __('Modules les plus adoptés') }}</div>
            @forelse ($moduleAdoption as $mod)
            <div class="flex items-center gap-2.5 mb-2.5 last:mb-0">
                <span class="text-xs font-semibold text-slate-600 w-[120px] shrink-0 truncate" title="{{ $mod->label }}">{{ $mod->label }}</span>
                <div class="flex-1 h-2 bg-slate-100 rounded overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded" style="width:{{ $maxAdoption > 0 ? round($mod->tenant_count / $maxAdoption * 100) : 0 }}%;"></div>
                </div>
                <span class="text-xs font-bold text-slate-500 w-7 text-right">{{ $mod->tenant_count }}</span>
            </div>
            @empty
            <div class="text-sm text-slate-400 text-center py-5">{{ __('Aucun module activé') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Monthly bar chart --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-5">
        <div class="text-[13px] font-bold text-slate-900 mb-4">{{ __('Entreprises créées — 6 derniers mois') }}</div>
        @if ($monthlyCreated->count())
        <div class="flex items-end gap-2.5 h-36 pb-6">
            @foreach ($monthlyCreated as $m)
            @php $barH = $maxMonthly > 0 ? round($m->count / $maxMonthly * 120) : 0; @endphp
            <div class="flex-1 flex flex-col items-center justify-end h-full">
                <span class="text-[10px] font-bold text-indigo-500 mb-1">{{ $m->count }}</span>
                <div class="w-full rounded-t bg-indigo-500 opacity-80 min-h-[2px]" style="height:{{ max($barH, 2) }}px;"></div>
                <span class="text-[9px] text-slate-400 mt-1 whitespace-nowrap">{{ $m->month_label }}</span>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-sm text-slate-400 text-center py-5">{{ __('Aucune entreprise créée sur la période.') }}</div>
        @endif
    </div>

    {{-- Recent tenants --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <span class="text-[13px] font-bold text-slate-900">{{ __('Dernières entreprises') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5">{{ __('Entreprise') }}</th>
                        <th class="text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5">{{ __('Plan') }}</th>
                        <th class="text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5">{{ __('Provisionnement') }}</th>
                        <th class="text-left text-[10px] font-bold uppercase tracking-wider text-slate-400 px-5 py-2.5">{{ __('Créée le') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentTenants as $t)
                    @php
                        $provClass = match($t->provisioning_status ?? '') {
                            'completed'    => 'bg-emerald-400',
                            'provisioning' => 'bg-amber-400',
                            'failed'       => 'bg-red-500',
                            default        => 'bg-slate-300',
                        };
                        $provLabel = match($t->provisioning_status ?? '') {
                            'completed'    => __('OK'),
                            'provisioning' => __('En cours'),
                            'failed'       => __('Échec'),
                            'pending'      => __('En attente'),
                            default        => '—',
                        };
                        $planBg = match($t->plan ?? 'free') {
                            'starter'    => 'bg-blue-50 text-blue-700',
                            'pro'        => 'bg-violet-50 text-violet-700',
                            'enterprise' => 'bg-fuchsia-50 text-fuchsia-700',
                            default      => 'bg-slate-100 text-slate-500',
                        };
                    @endphp
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-2.5 font-semibold text-slate-800">{{ $t->name }}</td>
                        <td class="px-5 py-2.5 font-mono text-indigo-600">{{ $t->code }}</td>
                        <td class="px-5 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold {{ $planBg }}">{{ $t->planLabel() }}</span>
                            @if ($t->isTrialing()) <span class="text-[10px] text-amber-600 ml-1">essai</span> @endif
                            @if ($t->isExpired())  <span class="text-[10px] text-red-500 ml-1">expiré</span>  @endif
                        </td>
                        <td class="px-5 py-2.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full {{ $provClass }} mr-1.5"></span>
                            {{ $provLabel }}
                        </td>
                        <td class="px-5 py-2.5 text-slate-400">{{ $t->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
