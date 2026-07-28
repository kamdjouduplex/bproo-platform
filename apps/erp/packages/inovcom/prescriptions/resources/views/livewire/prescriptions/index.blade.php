@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <h2 class="card-title">Ordonnances</h2>
        <div style="margin-bottom: 16px;">
            <a class="btn btn-primary" href="{{ route('tenant.prescriptions.create', ['tenant' => $tenantCode]) }}">Nouvelle ordonnance</a>
        </div>
        <div class="form-grid" style="margin-bottom: 16px;">
            <div class="field">
                <label class="field-label">Recherche (n° ou client)</label>
                <input class="input" wire:model="search" placeholder="N° ordonnance, client…">
            </div>
            <div class="field">
                <label class="field-label">Statut</label>
                <select class="input" wire:model="statusFilter">
                    <option value="">Tous</option>
                    <option value="draft">Brouillon</option>
                    <option value="active">Active</option>
                    <option value="dispensed">Dispensée</option>
                    <option value="expired">Expirée</option>
                    <option value="cancelled">Annulée</option>
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
                        <th>N°</th>
                        <th>Client</th>
                        <th>Prescripteur</th>
                        <th>Valide jusqu'au</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prescriptions as $p)
                        <tr>
                            <td>{{ $p->number }}</td>
                            <td>{{ $p->client?->name ?? '—' }}</td>
                            <td>{{ $p->prescriber_name ?? '—' }}</td>
                            <td>{{ $p->valid_until?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $p->status }}</td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('tenant.prescriptions.edit', ['prescription' => $p->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Aucune ordonnance.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $prescriptions->links() }}
        </div>
    </section>
</div>
