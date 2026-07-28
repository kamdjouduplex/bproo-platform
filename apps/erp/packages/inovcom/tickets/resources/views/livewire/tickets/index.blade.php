<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
        <div class="card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:#2563eb;">{{ $stats['open'] }}</div>
            <div style="font-size:12px;color:#6b7280;">Ouverts</div>
        </div>
        <div class="card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:#d97706;">{{ $stats['in_progress'] }}</div>
            <div style="font-size:12px;color:#6b7280;">En cours</div>
        </div>
        <div class="card" style="padding:14px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:#16a34a;">{{ $stats['resolved'] }}</div>
            <div style="font-size:12px;color:#6b7280;">Résolus</div>
        </div>
    </div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Tickets</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input class="input input-sm" wire:model.live.debounce.300ms="search" placeholder="Rechercher…" style="min-width:180px;">
                <select class="input input-sm" wire:model.live="statusFilter">
                    <option value="all">Tous statuts</option>
                    @foreach (\InovCom\Tickets\Models\Ticket::statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select class="input input-sm" wire:model.live="priorityFilter">
                    <option value="all">Toutes priorités</option>
                    @foreach (\InovCom\Tickets\Models\Ticket::priorityOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select class="input input-sm" wire:model.live="assignedFilter">
                    <option value="all">Tous</option>
                    <option value="mine">Mes tickets</option>
                    <option value="unassigned">Non assignés</option>
                </select>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.tickets.create', ['tenant' => $tenantCode]) }}">Nouveau ticket</a>
                @endif
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Titre</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                        <th>Assigné</th>
                        <th>Mis à jour</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><strong>{{ $ticket->ticket_number }}</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($ticket->title, 50) }}</td>
                            <td>
                                @php
                                    $prioClass = match($ticket->priority) {
                                        'urgent' => 'background:#fef2f2;color:#b91c1c;',
                                        'high' => 'background:#fff7ed;color:#c2410c;',
                                        default => 'background:#f3f4f6;color:#374151;',
                                    };
                                @endphp
                                <span class="badge" style="{{ $prioClass }}">{{ \InovCom\Tickets\Models\Ticket::priorityLabel($ticket->priority) }}</span>
                            </td>
                            <td>{{ \InovCom\Tickets\Models\Ticket::statusLabel($ticket->status) }}</td>
                            <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td>{{ $ticket->updated_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.tickets.show', ['ticket' => $ticket->id, 'tenant' => $tenantCode]) }}">Voir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center;color:#6b7280;">Aucun ticket.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $tickets->links() }}</div>
    </section>
</div>
