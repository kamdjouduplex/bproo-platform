@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Fournisseurs</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model.live.debounce.350ms="search" placeholder="Nom, code, téléphone ou email" style="min-width: 220px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model.live="perPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                @if ($canExport ?? true)
                    <x-export-btn format="excel" class="btn-sm" wire:click="exportExcel">Exporter Excel</x-export-btn>
                    <x-export-btn format="pdf" class="btn-sm" wire:click="exportPdf">Exporter PDF</x-export-btn>
                @endif
                @if ($canCreate ?? true)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.providers.create', ['tenant' => $tenantCode]) }}">Nouveau</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($providers as $provider)
                        <tr>
                            <td><strong>{{ $provider->code }}</strong></td>
                            <td>{{ $provider->name }}</td>
                            <td>{{ $provider->phone ?? '—' }}</td>
                            <td>{{ $provider->email ?? '—' }}</td>
                            <td>
                                @if ($provider->is_active)
                                    <span class="badge badge-success">Actif</span>
                                @else
                                    <span class="badge badge-warning">Inactif</span>
                                @endif
                            </td>
                            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.providers.show', [$provider->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.providers.edit', [$provider->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @if ($canDelete ?? true)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $provider->id }})" onclick="return confirm('Supprimer ce fournisseur ?')">Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($providers->count() === 0)
                        <tr>
                            <td colspan="6">Aucun fournisseur pour le moment.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $providers->appends(['tenant' => $tenantCode])->links() }}
        </div>
    </section>
</div>
