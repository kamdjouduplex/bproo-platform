@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;

    $statusLabels = [
        'all' => 'Tous',
        'draft' => 'Brouillon',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ];
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    <section class="card" style="margin-bottom: 16px; padding: 14px 16px;">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
            <div>
                <div style="font-weight:700; color:#0f172a;">Feuille de comptage papier</div>
                <p style="margin:4px 0 0; font-size:13px; color:#64748b; max-width:52rem;">
                    Exportez le catalogue actif (réf., désignation, unité, stock système, colonne « Qté comptée » vide)
                    pour compter en rayon, puis saisissez les quantités dans un inventaire démarré.
                </p>
            </div>
            <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                <label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#475569;">
                    <input type="checkbox" wire:model.live="blindExport">
                    Comptage à l’aveugle (masquer stock système)
                </label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <x-export-btn format="excel" class="btn-sm" wire:click="exportPaperExcel">Feuille Excel</x-export-btn>
                    <x-export-btn format="pdf" class="btn-sm" wire:click="exportPaperPdf">Feuille PDF</x-export-btn>
                </div>
            </div>
        </div>
    </section>

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Inventaires</h2>
            <div class="client-list-head__actions">
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.inventory.create', ['tenant' => $tenantCode]) }}">Nouvel inventaire</a>
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input
                    class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Référence ou titre…"
                    aria-label="Rechercher un inventaire"
                >
            </div>
            <div class="client-filter-bar__tools">
                @if ($search !== '' || $statusFilter !== 'all')
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinit.</button>
                @endif
                <label class="client-filter-bar__per-page">
                    <span class="sr-only">Par page</span>
                    <select class="input input-sm" wire:model.live="perPage" aria-label="Par page">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="client-status-pills" role="group" aria-label="Filtrer par statut">
            @foreach ($statusLabels as $value => $label)
                <button
                    type="button"
                    class="client-status-pill {{ $statusFilter === $value ? 'client-status-pill--active' : '' }}"
                    wire:click="setStatusFilter('{{ $value }}')"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th>Démarré le</th>
                        <th>Terminé le</th>
                        <th>Progression</th>
                        <th>Différence totale</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($counts as $count)
                        @php $progress = $count->progress_percentage ?? 0; @endphp
                        <tr wire:key="inv-{{ $count->id }}">
                            <td><strong>{{ $count->reference }}</strong></td>
                            <td>{{ $count->title }}</td>
                            <td>
                                @if ($count->status === 'draft')
                                    <span class="badge badge-secondary">Brouillon</span>
                                @elseif ($count->status === 'in_progress')
                                    <span class="badge badge-warning">En cours</span>
                                @elseif ($count->status === 'completed')
                                    <span class="badge badge-success">Terminé</span>
                                @else
                                    <span class="badge badge-error">Annulé</span>
                                @endif
                            </td>
                            <td>{{ $count->started_at ? $count->started_at->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $count->completed_at ? $count->completed_at->format('d/m/Y H:i') : '—' }}</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: {{ $progress }}%; background: {{ $progress == 100 ? '#16a34a' : '#3b82f6' }};"></div>
                                    </div>
                                    <span style="font-size: 12px; color: #6b7280;">{{ fmt_num($progress, 1) }}%</span>
                                </div>
                            </td>
                            <td>
                                @php
                                    $totalDiff = $count->total_difference ?? 0;
                                    $totalValueDiff = $count->total_value_difference ?? 0;
                                @endphp
                                @if ($totalDiff != 0)
                                    <div>
                                        <span style="color: {{ $totalDiff > 0 ? '#16a34a' : '#dc2626' }};">
                                            {{ $totalDiff > 0 ? '+' : '' }}{{ fmt_num($totalDiff) }}
                                        </span>
                                        <br>
                                        <small style="color: #6b7280;">{{ fmt_money($totalValueDiff) }} {{ currency_label() }}</small>
                                    </div>
                                @else
                                    <span style="color: #6b7280;">—</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.inventory.edit', [$count->id, 'tenant' => $tenantCode]) }}">
                                    {{ $count->isDraft() ? 'Modifier' : 'Voir' }}
                                </a>
                                @if ($count->isDraft() || $count->isCancelled())
                                    <button
                                        class="btn btn-secondary btn-sm"
                                        wire:click="delete({{ $count->id }})"
                                        wire:confirm="Supprimer cet inventaire ?"
                                    >Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($counts->count() === 0)
                        <tr>
                            <td colspan="8">Aucun inventaire pour ces filtres.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $counts->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
