@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $alertLabels = [
        'expired' => 'Périmé',
        'd30' => '≤ 30 j',
        'd90' => '≤ 90 j',
        'd180' => '≤ 6 mois',
        'ok' => 'OK',
    ];
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:8px;">
            {{ session('error') }}
        </div>
    @endif

    <section class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div>
                <h2 class="card-title" style="margin: 0;">Lots et dates de péremption</h2>
                <p style="margin:6px 0 0;font-size:13px;color:#64748b;">
                    Les lots périmés ne peuvent plus être vendus au POS.
                    Utilisez <strong>Sortir du stock</strong> pour les détruire et mettre à jour le stock.
                </p>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                @if($canWriteOff && ($stats['expired'] ?? 0) > 0)
                    <button
                        type="button"
                        class="btn btn-secondary"
                        wire:click="writeOffAllExpired"
                        wire:confirm="Sortir TOUS les lots périmés encore en stock ? Le stock article sera diminué et une perte sera enregistrée pour chaque lot."
                        wire:loading.attr="disabled"
                    >
                        Sortir tous les périmés ({{ $stats['expired'] }})
                    </button>
                @endif
                <a class="btn btn-primary" href="{{ route('tenant.batches.create', ['tenant' => $tenantCode]) }}">Nouveau lot</a>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
            <button type="button" class="btn btn-sm {{ $filter === 'expired' ? 'btn-primary' : 'btn-secondary' }}" wire:click="$set('filter', 'expired')">
                Périmés ({{ $stats['expired'] }})
            </button>
            <button type="button" class="btn btn-sm {{ $filter === 'd30' ? 'btn-primary' : 'btn-secondary' }}" wire:click="$set('filter', 'd30')">
                ≤ 30 jours ({{ $stats['d30'] }})
            </button>
            <button type="button" class="btn btn-sm {{ $filter === 'd90' ? 'btn-primary' : 'btn-secondary' }}" wire:click="$set('filter', 'd90')">
                ≤ 90 jours ({{ $stats['d90'] }})
            </button>
            <button type="button" class="btn btn-sm {{ $filter === 'd180' ? 'btn-primary' : 'btn-secondary' }}" wire:click="$set('filter', 'd180')">
                ≤ 6 mois ({{ $stats['d180'] }})
            </button>
            <button type="button" class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-secondary' }}" wire:click="$set('filter', 'all')">
                Tous
            </button>
        </div>

        <div class="form-grid" style="margin-bottom: 16px;">
            <div class="field">
                <label class="field-label">Article (référence, désignation, code-barres)</label>
                <input class="input" wire:model.live.debounce.300ms="search" placeholder="Rechercher…">
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>N° lot</th>
                        <th>Péremption</th>
                        <th>Alerte</th>
                        <th>Quantité</th>
                        <th>Reçu le</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        @php $level = $batch->expiryAlertLevel(); @endphp
                        <tr>
                            <td>{{ $batch->item?->name ?? '—' }}</td>
                            <td>{{ $batch->batch_number }}</td>
                            <td>
                                <span @style(['color: #b91c1c; font-weight: 600' => $level === 'expired', 'color: #b45309; font-weight: 600' => $level === 'd30'])>
                                    {{ $batch->expiry_date->format('d/m/Y') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $level === 'expired' ? 'badge-error' : ($level === 'd30' ? 'badge-warning' : ($level === 'ok' ? 'badge-success' : 'badge-secondary')) }}">
                                    {{ $alertLabels[$level] ?? $level }}
                                </span>
                            </td>
                            <td>{{ fmt_num($batch->quantity) }}</td>
                            <td>{{ $batch->received_at?->format('d/m/Y') ?? '—' }}</td>
                            <td style="white-space: nowrap; text-align: right;">
                                @if($canEditExpiry)
                                    <a
                                        class="btn btn-sm btn-secondary"
                                        href="{{ route('tenant.batches.edit', [$batch->id, 'tenant' => $tenantCode]) }}"
                                        title="Modifier le lot"
                                    >
                                        Modifier
                                    </a>
                                @endif
                                @if($canWriteOff && $level === 'expired' && (float) $batch->quantity > 0)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-secondary"
                                        style="border-color:#fecaca;color:#b91c1c;"
                                        wire:click="writeOffExpired({{ $batch->id }})"
                                        wire:confirm="Sortir le lot {{ $batch->batch_number }} ({{ fmt_num($batch->quantity) }}) du stock ? Cette action diminue le stock article et enregistre une perte « produit expiré »."
                                        wire:loading.attr="disabled"
                                    >
                                        Sortir du stock
                                    </button>
                                @elseif($level === 'expired' && (float) $batch->quantity <= 0)
                                    <span style="font-size:12px;color:#64748b;">Déjà sorti</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @if($filter === 'expired')
                                    Aucun lot périmé encore en stock. Tout est à jour.
                                @else
                                    Aucun lot pour ce filtre. Enregistrez des lots à la réception d’achats ou via « Nouveau lot ».
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-pagination">
            {{ $batches->links() }}
        </div>
    </section>
</div>
