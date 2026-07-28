@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Réservations produits</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <form wire:submit.prevent="applySearch" style="display:inline-flex; gap:4px;">
                    <input class="input input-sm" wire:model="search" placeholder="Réf., client…" style="min-width:200px;">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous statuts</option>
                    <option value="active">Actives</option>
                    <option value="converted">Converties</option>
                    <option value="cancelled">Annulées</option>
                </select>
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.reservations.create', ['tenant' => $tenantCode]) }}">Nouvelle réservation</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Prévue le</th>
                        <th>Statut</th>
                        <th>Créée par</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $res)
                        <tr>
                            <td><strong>{{ $res->reference }}</strong></td>
                            <td>{{ $res->client?->name ?? '—' }}</td>
                            <td>{{ $res->reservation_date->format('d/m/Y') }}</td>
                            <td>{{ $res->expected_date?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge badge-secondary">{{ $res->status_label }}</span></td>
                            <td>{{ $res->creator?->name ?? '—' }}</td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.reservations.show', [$res->id, 'tenant' => $tenantCode]) }}">Ouvrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune réservation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $reservations->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
