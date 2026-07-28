<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Statut des bases vendeurs</div>
            <button class="btn btn-secondary" wire:click="refreshStatuses">Rafraîchir</button>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Vendeur</th>
                        <th>Code</th>
                        <th>DB</th>
                        <th>Statut</th>
                        <th>Message</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statuses as $status)
                        <tr>
                            <td>{{ $status['name'] }}</td>
                            <td>{{ $status['code'] }}</td>
                            <td>{{ $status['db_name'] }}</td>
                            <td>
                                @if ($status['status'] === 'ok')
                                    <span class="badge badge-success">OK</span>
                                @elseif ($status['status'] === 'pending')
                                    <span class="badge badge-warning">En cours</span>
                                @else
                                    <span class="badge badge-warning">Erreur</span>
                                @endif
                            </td>
                            <td>{{ $status['message'] }}</td>
                            <td>
                                @if (in_array($status['provisioning_status'] ?? '', ['failed', 'pending'], true))
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        wire:click="retryProvisioning('{{ $status['code'] }}')"
                                        wire:loading.attr="disabled"
                                    >
                                        Relancer
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if (empty($statuses))
                        <tr>
                            <td colspan="6">Aucun vendeur.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
</div>
