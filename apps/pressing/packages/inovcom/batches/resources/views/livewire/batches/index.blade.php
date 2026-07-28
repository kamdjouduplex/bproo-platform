@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <h2 class="card-title" style="margin: 0;">Lots et dates de péremption</h2>
            <a class="btn btn-primary" href="{{ route('tenant.batches.create', ['tenant' => $tenantCode]) }}">Nouveau lot</a>
        </div>
        <div class="form-grid" style="margin-bottom: 16px;">
            <div class="field">
                <label class="field-label">Article (référence, désignation, code-barres)</label>
                <input class="input" wire:model="search" placeholder="Rechercher…">
            </div>
            <div class="field">
                <label class="field-label">Filtre</label>
                <select class="input" wire:model="filter">
                    <option value="all">Tous</option>
                    <option value="near_expiry">Péremption sous 90 jours</option>
                    <option value="expired">Périmés (avec stock)</option>
                </select>
            </div>
            <div class="field" style="align-self: end;">
                <button type="button" class="btn btn-primary" wire:click="applyFilters">Appliquer</button>
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>N° lot</th>
                        <th>Péremption</th>
                        <th>Quantité</th>
                        <th>Reçu le</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td>{{ $batch->item?->name ?? '—' }}</td>
                            <td>{{ $batch->batch_number }}</td>
                            <td>
                                <span class="{{ $batch->isExpired() ? 'text-red-600' : '' }}">
                                    {{ $batch->expiry_date->format('d/m/Y') }}
                                </span>
                            </td>
                            <td>{{ fmt_num($batch->quantity) }}</td>
                            <td>{{ $batch->received_at?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Aucun lot. Cliquez sur « Nouveau lot » pour enregistrer un lot manuellement, ou enregistrez-les à la réception d'achats.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $batches->links() }}
        </div>
    </section>
</div>
