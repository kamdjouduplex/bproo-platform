@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;

    $tableLabels = [
        'clients' => 'Clients', 'offers' => 'Offres', 'quotes' => 'Devis',
        'projects' => 'Projets', 'invoices' => 'Factures', 'purchase_orders' => 'Achats',
        'maintenance_contracts' => 'Contrats maintenance', 'maintenance_orders' => 'Ordres maintenance',
        'interventions' => 'Interventions', 'documents' => 'Documents', 'site_reports' => 'Rapports terrain',
    ];
    $eventMeta = [
        'created'        => ['label' => 'Créé',     'class' => 'badge-success'],
        'updated'        => ['label' => 'Modifié',   'class' => 'badge-info'],
        'deleted'        => ['label' => 'Supprimé',  'class' => 'badge-danger'],
        'status_changed' => ['label' => 'Statut',    'class' => 'badge-warning'],
    ];
@endphp
<div>
    {{-- Filters --}}
    <div class="card mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <select class="input input-sm" wire:model.live="filterTable" style="min-width:160px;">
                <option value="">{{ __('Toutes les entités') }}</option>
                @foreach ($tables as $t)
                    <option value="{{ $t }}">{{ $tableLabels[$t] ?? $t }}</option>
                @endforeach
            </select>
            <select class="input input-sm" wire:model.live="filterEvent" style="min-width:130px;">
                <option value="">{{ __('Tous les événements') }}</option>
                <option value="created">{{ __('Créé') }}</option>
                <option value="updated">{{ __('Modifié') }}</option>
                <option value="deleted">{{ __('Supprimé') }}</option>
                <option value="status_changed">{{ __('Changement statut') }}</option>
            </select>
            <select class="input input-sm" wire:model.live="filterUser" style="min-width:150px;">
                <option value="">{{ __('Tous les utilisateurs') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" class="input input-sm" wire:model.live="dateFrom" style="width:140px;">
            <span class="text-slate-400 text-xs">→</span>
            <input type="date" class="input input-sm" wire:model.live="dateTo" style="width:140px;">
            <select class="input input-sm" wire:model.live="perPage" style="width:70px;">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">{{ __('Réinitialiser') }}</button>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 bg-slate-50">
            <span class="text-sm font-semibold text-slate-700">{{ __('Journal d\'audit') }}</span>
            <span class="text-xs text-slate-400">{{ $logs->total() }} {{ __('entrée(s)') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12px]">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Date / heure') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Utilisateur') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Entité') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('ID') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Événement') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5 max-w-[420px]">{{ __('Modifications') }}</th>
                        <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('IP') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                    @php
                        $em  = $eventMeta[$log->event] ?? ['label' => $log->event, 'class' => 'badge-secondary'];
                        $old = $log->old_values ? json_decode($log->old_values, true) : [];
                        $new = $log->new_values ? json_decode($log->new_values, true) : [];
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-2.5 whitespace-nowrap text-slate-400">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2.5 text-slate-600">
                            @if ($log->user_id)
                                {{ $userMap[$log->user_id] ?? '#'.$log->user_id }}
                            @else
                                <span class="text-slate-300">Système</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="text-[11px] font-semibold text-slate-500">{{ $tableLabels[$log->auditable_type] ?? $log->auditable_type }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $log->auditable_id }}</td>
                        <td class="px-4 py-2.5">
                            <span class="badge {{ $em['class'] }}">{{ $em['label'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 max-w-[420px]">
                            @if ($log->event === 'created' && !empty($new))
                                <span class="text-[11px] text-slate-400">{{ count($new) }} champ(s) initialisé(s)</span>
                            @elseif ($log->event === 'deleted' && !empty($old))
                                <span class="text-[11px] text-slate-400">{{ count($old) }} champ(s) supprimé(s)</span>
                            @elseif (!empty($old) || !empty($new))
                                <div class="flex flex-col gap-0.5">
                                    @foreach (array_keys(array_merge($old, $new)) as $field)
                                    @if (array_key_exists($field, $old) || array_key_exists($field, $new))
                                    <div class="flex gap-1.5 items-baseline flex-wrap text-[11px]">
                                        <span class="text-slate-500 font-semibold min-w-[80px]">{{ $field }}</span>
                                        @if (isset($old[$field]))
                                            <span class="text-red-500 line-through max-w-[140px] overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $old[$field] }}">
                                                {{ \Illuminate\Support\Str::limit((string)$old[$field], 30) }}
                                            </span>
                                        @endif
                                        @if (isset($new[$field]))
                                            <span class="text-emerald-600 max-w-[140px] overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $new[$field] }}">
                                                → {{ \Illuminate\Support\Str::limit((string)$new[$field], 30) }}
                                            </span>
                                        @endif
                                    </div>
                                    @endif
                                    @endforeach
                                </div>
                            @else
                                <span class="text-slate-300 text-[11px]">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-[11px] whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">{{ __('Aucune entrée d\'audit pour ces critères.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
