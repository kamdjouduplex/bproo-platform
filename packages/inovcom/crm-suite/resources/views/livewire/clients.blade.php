@include('inovcom-crm::partials.styles')
<div class="page-body crm-v2">
    <div class="crm-v2-head">
        <div>
            <h2>Clients</h2>
            <p>Vue relationnelle CRM. Devis, factures et paiements restent dans l’ERP.</p>
        </div>
        <div class="crm-v2-actions">
            @if (Route::has('tenant.clients.index'))
                <a class="btn btn-secondary" href="{{ route('tenant.clients.index', ['tenant' => $tenantCode]) }}">Fiches ERP</a>
            @endif
        </div>
    </div>
    <div class="crm-filterbar">
        <input class="input crm-filterbar__search" type="search" wire:model.live.debounce.250ms="search" placeholder="Rechercher un client…">
    </div>
    <div class="crm-table-wrap">
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Téléphone</th>
                    <th>Commercial</th>
                    <th>Opportunités</th>
                    <th>Dernière interaction</th>
                    <th>Prochaine action</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($clients as $client)
                @php
                    $prospect = $prospectsByClient[$client->id] ?? null;
                    $opps = $oppsByClient[$client->id] ?? collect();
                    $next = $prospect?->nextPlannedActivity;
                @endphp
                <tr>
                    <td>
                        <div class="crm-person">
                            <span class="crm-avatar">{{ mb_strtoupper(mb_substr($client->name, 0, 2)) }}</span>
                            <div>
                                <strong>{{ $client->name }}</strong>
                                <span>{{ $client->code }}</span>
                            </div>
                        </div>
                    </td>
                    <td>{{ $client->phone ?: '—' }}</td>
                    <td>{{ $prospect?->owner?->name ?? '—' }}</td>
                    <td>{{ $opps->count() }}</td>
                    <td>{{ $prospect?->last_contacted_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @if ($next)
                            <div class="crm-next"><strong>{{ $next->displayTitle() }}</strong><time>{{ $next->due_at?->format('d/m/Y') }}</time></div>
                        @else — @endif
                    </td>
                    <td>
                        @if ($prospect)
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.prospects.show', ['tenant' => $tenantCode, 'prospect' => $prospect->id]) }}">Fiche CRM</a>
                        @elseif (Route::has('tenant.clients.show'))
                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.clients.show', ['tenant' => $tenantCode, 'client' => $client->id]) }}">Fiche ERP</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="crm-empty">Aucun client.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:12px;">{{ $clients->links() }}</div>
</div>
