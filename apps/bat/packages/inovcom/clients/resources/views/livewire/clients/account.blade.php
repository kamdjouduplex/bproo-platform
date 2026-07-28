@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div>

    {{-- ── Search filters ─────────────────────────────────────────────── --}}
    <div class="card mb-4">
        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 text-blue-600 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            {{ __('Recherche multi-critères') }}
        </h3>
        <div class="grid gap-3 items-end" style="grid-template-columns:2fr 1.5fr 1fr 1fr auto;">

            <div class="field">
                <label class="field-label">{{ __('Nom, email ou téléphone') }}</label>
                <input class="input" wire:model.live.debounce.350ms="search" placeholder="{{ __('Rechercher par nom…') }}" autocomplete="off">
            </div>

            <div class="field">
                <label class="field-label">{{ __('Code ou ID client') }}</label>
                <input class="input" wire:model.live.debounce.350ms="code" placeholder="{{ __('Ex. CLI-0042') }}" autocomplete="off">
            </div>

            <div class="field">
                <label class="field-label">{{ __('Type') }}</label>
                <select class="input" wire:model.live="type">
                    <option value="">{{ __('Tous') }}</option>
                    <option value="company">{{ __('Entreprise') }}</option>
                    <option value="individual">{{ __('Particulier') }}</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Statut') }}</label>
                <select class="input" wire:model.live="status">
                    <option value="">{{ __('Tous') }}</option>
                    <option value="active">{{ __('Actifs') }}</option>
                    <option value="inactive">{{ __('Inactifs') }}</option>
                </select>
            </div>

            <div class="pb-0">
                @if ($hasFilters)
                    <button type="button" class="btn btn-secondary w-full" wire:click="resetFilters" title="{{ __('Réinitialiser les filtres') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('Réinitialiser') }}
                    </button>
                @else
                    <button type="button" class="btn btn-secondary w-full opacity-40 cursor-default" disabled>{{ __('Réinitialiser') }}</button>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Empty state ─────────────────────────────────────────────────── --}}
    @if (!$hasFilters)
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center bg-white border border-slate-200 rounded-xl">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-14 h-14 text-slate-300 mb-5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-base font-semibold text-slate-700 mb-2">{{ __('Recherchez un client') }}</p>
            <p class="text-[13px] text-slate-400 max-w-sm leading-relaxed">
                {{ __('Utilisez les filtres ci-dessus pour retrouver un client et accéder à l\'ensemble de ses activités avec votre entreprise — offres, devis, projets, factures et bien plus.') }}
            </p>
        </div>

    {{-- ── Results table ────────────────────────────────────────────────── --}}
    @else
        <div class="card overflow-hidden p-0">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-slate-50">
                <span class="text-sm font-semibold text-slate-700">{{ __(':n client(s) trouvé(s)', ['n' => $clients->total()]) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Nom') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Type') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Email') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Téléphone') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="account-{{ $client->id }}">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ $client->code }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $client->name }}</td>
                            <td class="px-4 py-2.5">
                                @if ($client->type === 'company')
                                    <span class="badge badge-info">{{ __('Entreprise') }}</span>
                                @else
                                    <span class="badge" style="background:#f3e8ff;color:#7c3aed;">{{ __('Particulier') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $client->email ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $client->phone ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @if ($client->is_active)
                                    <span class="badge badge-success">{{ __('Actif') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Inactif') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('tenant.clients.compte.view', ['tenant' => $tenantCode, 'client' => $client->id]) }}"
                                   class="btn btn-primary btn-sm whitespace-nowrap">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('Vue 360°') }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                        @if ($clients->count() === 0)
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-8 h-8 text-slate-300 mx-auto mb-2"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <span class="text-slate-400 text-sm">{{ __('Aucun client ne correspond à vos critères.') }}</span>
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($clients->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">
                    {{ $clients->appends(['tenant' => $tenantCode])->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
