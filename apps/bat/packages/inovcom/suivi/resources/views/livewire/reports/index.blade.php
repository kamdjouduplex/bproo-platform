@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp
<div>
    {{-- ── Tab navigation ─────────────────────────────────────────────────── --}}
    <div class="flex gap-1 border-b border-slate-200 mb-5">
        <a href="{{ route('tenant.suivi.board', ['tenant' => $tenantCode]) }}"
           class="module-tab" wire:navigate>
            {{ __('Kanban') }}
        </a>
        <a href="{{ route('tenant.suivi.index', ['tenant' => $tenantCode]) }}"
           class="module-tab module-tab--active" wire:navigate>
            {{ __('Rapports terrain') }}
        </a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}"
                wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="project_id">
                <option value="">{{ __('Tous les projets') }}</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->title }}</option>
                @endforeach
            </select>
            <select class="input input-sm" wire:model.live="status">
                <option value="">{{ __('Tous les statuts') }}</option>
                <option value="draft">{{ __('Brouillon') }}</option>
                <option value="submitted">{{ __('Soumis') }}</option>
                <option value="validated">{{ __('Validé') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="15">15</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        @if ($canCreate)
            <a class="btn btn-primary" href="{{ route('tenant.suivi.create', ['tenant' => $tenantCode]) }}">
                + {{ __('Nouveau rapport') }}
            </a>
        @endif
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Date') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Projet') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Météo') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Effectif') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Avancement') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('PV') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Responsable') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $statusBg = match($report->status) {
                                'draft'     => 'bg-slate-100 text-slate-600',
                                'submitted' => 'bg-blue-100 text-blue-700',
                                'validated' => 'bg-emerald-100 text-emerald-700',
                                default     => 'bg-slate-100 text-slate-500',
                            };
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="report-{{ $report->id }}">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('tenant.suivi.edit', ['tenant' => $tenantCode, 'site_report' => $report->id]) }}"
                                   class="font-mono text-[11px] font-semibold text-blue-600 hover:text-blue-800">
                                    {{ $report->code }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $report->report_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5 text-slate-600 text-[11px]">{{ $report->project?->code }} — {{ \Illuminate\Support\Str::limit($report->project?->title, 25) }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $report->client?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500" title="{{ $report->weatherLabel() }}">{{ $report->weatherIcon() }} {{ $report->weatherLabel() }}</td>
                            <td class="px-4 py-2.5 text-center text-slate-700 font-medium">{{ $report->workers_count }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <div class="flex-1 bg-slate-100 rounded h-1.5 min-w-[60px]">
                                        <div class="bg-indigo-600 h-1.5 rounded" style="width:{{ $report->progress_percent }}%;"></div>
                                    </div>
                                    <span class="text-[11px] text-slate-500 whitespace-nowrap">{{ $report->progress_percent }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($report->pv_signed)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700" title="{{ __('Signé par') }} {{ $report->pv_client_name }}">✓ {{ __('Signé') }}</span>
                                @else
                                    <span class="text-[11px] text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBg }}">{{ $report->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $report->assignedUser?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <a class="table-action table-action-edit"
                                       href="{{ route('tenant.suivi.edit', ['tenant' => $tenantCode, 'site_report' => $report->id]) }}"
                                       title="{{ __('Modifier') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if ($canDelete && $report->status === 'draft')
                                        <button class="table-action table-action-delete"
                                            wire:click="delete({{ $report->id }})"
                                            wire:confirm="{{ __('Supprimer ce rapport ?') }}"
                                            title="{{ __('Supprimer') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-slate-400 text-sm">
                                {{ __('Aucun rapport de chantier trouvé.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $reports->links() }}</div>
        @endif
    </div>
</div>
