@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Inventaires</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <form wire:submit.prevent="applyFilters" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Réf. ou titre" style="min-width: 180px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="statusFilter">
                    <option value="all">Tous</option>
                    <option value="draft">Brouillon</option>
                    <option value="in_progress">En cours</option>
                    <option value="completed">Terminé</option>
                    <option value="cancelled">Annulé</option>
                </select>
                <select class="input input-sm" wire:model="perPage">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" class="btn btn-primary" wire:click="applyFilters">Rechercher</button>
                <button type="button" class="btn btn-secondary" wire:click="resetFilters">Réinitialiser</button>
                <a class="btn btn-primary" href="{{ route('tenant.inventory.create', ['tenant' => $tenantCode]) }}">Nouvel inventaire</a>
            </div>
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
                        @php
                            $progress = $count->progress_percentage ?? 0;
                        @endphp
                        <tr>
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
                            <td>
                                @if ($count->started_at)
                                    {{ $count->started_at->format('d/m/Y H:i') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if ($count->completed_at)
                                    {{ $count->completed_at->format('d/m/Y H:i') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: {{ $progress }}%; background: {{ $progress === 100 ? '#16a34a' : '#3b82f6' }}; transition: width 0.3s;"></div>
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
                                        <small style="color: #6b7280;">
                                            {{ fmt_money($totalValueDiff) }} FCFA
                                        </small>
                                    </div>
                                @else
                                    <span style="color: #6b7280;">-</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('tenant.inventory.edit', [$count->id, 'tenant' => $tenantCode]) }}">
                                    {{ $count->isDraft() ? 'Modifier' : 'Voir' }}
                                </a>
                                @if ($count->isDraft() || $count->isCancelled())
                                    <button class="btn btn-secondary" wire:click="delete({{ $count->id }})" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet inventaire ?')">
                                        Supprimer
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($counts->count() === 0)
                        <tr>
                            <td colspan="8">Aucun inventaire pour le moment.</td>
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
