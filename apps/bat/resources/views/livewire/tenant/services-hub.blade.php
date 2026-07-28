<div class="page-body space-y-6">

    {{-- ── Cycle métier ─────────────────────────────────────────────────── --}}
    <div class="card bg-gradient-to-br from-slate-50 to-indigo-50/40 border-indigo-100">
        <h2 class="text-sm font-bold text-slate-800 mb-1">{{ __('Cycle commercial → exécution') }}</h2>
        <p class="text-xs text-slate-500 mb-4">{{ __('Standard BTP : chaque offre acceptée devient un service exécuté (chantier, maintenance ou prestation).') }}</p>
        <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
            <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-600">{{ __('Offre') }}</span>
            <span class="text-slate-300">→</span>
            <span class="px-2.5 py-1 rounded-full bg-white border border-slate-200 text-slate-600">{{ __('Devis') }}</span>
            <span class="text-slate-300">→</span>
            <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">{{ __('Acceptation') }}</span>
            <span class="text-slate-300">→</span>
            <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 border border-blue-200">{{ __('Chantier') }}</span>
            <span class="text-slate-400 font-normal">/</span>
            <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">{{ __('Maintenance') }}</span>
            <span class="text-slate-400 font-normal">/</span>
            <span class="px-2.5 py-1 rounded-full bg-violet-100 text-violet-800 border border-violet-200">{{ __('Prestation') }}</span>
            <span class="text-slate-300">→</span>
            <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-200">{{ __('Facturation') }}</span>
        </div>
    </div>

    {{-- ── Types de services ────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-sm font-bold text-slate-800 mb-3">{{ __('Types de services') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @if($can['projets'])
            <a href="{{ route('tenant.projets.index', ['tenant' => $tenantCode]) }}"
               class="card hover:border-blue-300 hover:shadow-md transition-all group no-underline">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-blue-700">{{ __('Chantiers & projets') }}</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ __('Travaux neufs, rénovation, lots, phases et suivi d\'avancement.') }}</p>
                        <p class="text-lg font-bold text-blue-700 mt-2 tabular-nums">{{ $stats['construction_active'] }} <span class="text-xs font-normal text-slate-500">{{ __('actifs') }}</span></p>
                    </div>
                </div>
            </a>

            <a href="{{ route('tenant.prestations.index', ['tenant' => $tenantCode]) }}"
               class="card hover:border-violet-300 hover:shadow-md transition-all group no-underline">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-violet-700">{{ __('Prestations ponctuelles') }}</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ __('Interventions courtes, dépannages, missions sans chantier long.') }}</p>
                        <p class="text-lg font-bold text-violet-700 mt-2 tabular-nums">{{ $stats['service_active'] }} <span class="text-xs font-normal text-slate-500">{{ __('actives') }}</span></p>
                    </div>
                </div>
            </a>
            @endif

            @if($can['maintenance'])
            <a href="{{ route('tenant.maintenance.orders.index', ['tenant' => $tenantCode]) }}"
               class="card hover:border-emerald-300 hover:shadow-md transition-all group no-underline">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-700">{{ __('Maintenance') }}</h3>
                        <p class="text-xs text-slate-500 mt-1">{{ __('Contrats SLA, ordres planifiés et interventions terrain.') }}</p>
                        <p class="text-lg font-bold text-emerald-700 mt-2 tabular-nums">{{ $stats['maintenance_open'] }} <span class="text-xs font-normal text-slate-500">{{ __('ordres ouverts') }}</span></p>
                        @if($stats['interventions_week'] > 0)
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ __(':n intervention(s) cette semaine', ['n' => $stats['interventions_week']]) }}</p>
                        @endif
                    </div>
                </div>
            </a>
            @endif
        </div>
    </div>

    {{-- ── Pilotage ─────────────────────────────────────────────────────── --}}
    @if($can['planning'] || $can['suivi'] || $can['stock'] || $can['logistique'])
    <div>
        <h2 class="text-sm font-bold text-slate-800 mb-3">{{ __('Pilotage & moyens') }}</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @if($can['planning'])
            <a href="{{ route('tenant.planning.index', ['tenant' => $tenantCode]) }}" class="px-4 py-3 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/30 text-sm font-medium text-slate-700 no-underline">{{ __('Planning') }}</a>
            @endif
            @if($can['suivi'])
            <a href="{{ route('tenant.suivi.board', ['tenant' => $tenantCode]) }}" class="px-4 py-3 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/30 text-sm font-medium text-slate-700 no-underline">{{ __('Suivi terrain') }}</a>
            @endif
            @if($can['stock'])
            <a href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}" class="px-4 py-3 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/30 text-sm font-medium text-slate-700 no-underline">{{ __('Stock') }}</a>
            @endif
            @if($can['logistique'])
            <a href="{{ route('tenant.logistique.index', ['tenant' => $tenantCode]) }}" class="px-4 py-3 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/30 text-sm font-medium text-slate-700 no-underline">{{ __('Logistique') }}</a>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Référentiel types ──────────────────────────────────────────────── --}}
    <div class="card bg-slate-50 border-dashed">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">{{ __('Référentiel des types') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-600">
            <div><strong class="text-blue-800">{{ __('Chantier') }}</strong> — {{ __('Marchés, lots, phases, achats chantier, PV réception.') }}</div>
            <div><strong class="text-emerald-800">{{ __('Maintenance') }}</strong> — {{ __('Contrats récurrents, SLA, ordres préventifs/correctifs.') }}</div>
            <div><strong class="text-violet-800">{{ __('Prestation') }}</strong> — {{ __('Mission ponctuelle, dépannage, intervention rapide facturable.') }}</div>
        </div>
    </div>
</div>
