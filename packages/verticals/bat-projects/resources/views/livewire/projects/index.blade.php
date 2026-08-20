@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isPrestations = $isPrestations ?? false;
    $createRoute = $isPrestations ? 'tenant.prestations.create' : 'tenant.projets.create';
@endphp
<div>
    {{-- ── Navigation types de services ─────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2 mb-4 border-b border-slate-200 pb-3">
        <a href="{{ route('tenant.services.index', ['tenant' => $tenantCode]) }}"
           class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 no-underline">{{ __('Centre des services') }}</a>
        <a href="{{ route('tenant.projets.index', ['tenant' => $tenantCode]) }}"
           class="text-[12px] px-3 py-1.5 rounded-lg font-medium no-underline {{ !$isPrestations ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ __('Chantiers') }}</a>
        <a href="{{ route('tenant.prestations.index', ['tenant' => $tenantCode]) }}"
           class="text-[12px] px-3 py-1.5 rounded-lg font-medium no-underline {{ $isPrestations ? 'bg-violet-100 text-violet-800 border border-violet-200' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ __('Prestations') }}</a>
        <a href="{{ route('tenant.maintenance.orders.index', ['tenant' => $tenantCode]) }}"
           class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 no-underline">{{ __('Maintenance') }}</a>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <input class="input input-sm" placeholder="{{ __('Recherche...') }}" wire:model.live.debounce.400ms="search">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="10">10</option><option value="25">25</option><option value="50">50</option>
            </select>
        </div>
        <a class="btn btn-primary" href="{{ route($createRoute, ['tenant' => $tenantCode]) }}">
            + {{ $isPrestations ? __('Nouvelle prestation') : __('Nouveau chantier') }}
        </a>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                        @if(!$isPrestations)
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Type') }}</th>
                        @endif
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Devis') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Début') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Fin') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Chef de projet') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                    @php
                        $sc = match($project->status) {
                            'planned'     => 'bg-slate-100 text-slate-600',
                            'in_progress' => 'bg-blue-100 text-blue-700',
                            'on_hold'     => 'bg-amber-100 text-amber-700',
                            'completed'   => 'bg-emerald-100 text-emerald-700',
                            'closed'      => 'bg-slate-100 text-slate-500',
                            default       => 'bg-slate-100 text-slate-500',
                        };
                        $sl = match($project->status) {
                            'planned'     => __('Planifié'),
                            'in_progress' => __('En cours'),
                            'on_hold'     => __('En attente'),
                            'completed'   => __('Terminé'),
                            'closed'      => __('Clôturé'),
                            default       => $project->status,
                        };
                        $ptype = $project->project_type ?? 'construction';
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="project-{{ $project->id }}">
                        <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">
                            <a class="hover:text-blue-700" href="{{ route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $project->id]) }}">{{ $project->code }}</a>
                        </td>
                        <td class="px-4 py-2.5 font-semibold text-slate-800">
                            <a class="hover:text-blue-700" href="{{ route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $project->id]) }}">{{ $project->title }}</a>
                        </td>
                        @if(!$isPrestations)
                        <td class="px-4 py-2.5">
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $catalog::executionBadgeClass($ptype) }}">
                                {{ $catalog::executionLabel($ptype) }}
                            </span>
                        </td>
                        @endif
                        <td class="px-4 py-2.5 text-slate-500">{{ $project->client?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $project->quote?->code ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $project->start_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $project->end_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $project->assignedUser?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5"><span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $sl }}</span></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1">
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $project->id]) }}"
                                   title="{{ __('Ouvrir') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a class="table-action table-action-edit"
                                   href="{{ route('tenant.projets.edit', ['tenant' => $tenantCode, 'project' => $project->id]) }}"
                                   title="{{ __('Modifier') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <button type="button" class="table-action table-action-delete"
                                        wire:click="delete({{ $project->id }})"
                                        wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ?') }}"
                                        title="{{ __('Supprimer') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $isPrestations ? 9 : 10 }}" class="px-4 py-8 text-center text-slate-400 text-sm">
                        {{ $isPrestations ? __('Aucune prestation ponctuelle.') : __('Aucun chantier ou projet.') }}
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $projects->appends(['tenant' => $tenantCode])->links() }}</div>
    </div>
</div>
