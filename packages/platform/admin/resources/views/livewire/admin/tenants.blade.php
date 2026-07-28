<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Liste des vendeurs</div>
            <a class="btn btn-primary" href="{{ route('system.tenants.create') }}">Nouveau</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Code</th>
                        <th>DB</th>
                        <th>Mode</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td>{{ $tenant['name'] }}</td>
                            <td>{{ $tenant['code'] }}</td>
                            <td>{{ $tenant['db_name'] }}</td>
                            <td>
                                @if (!empty($tenant['multi_store_enabled']))
                                    <span class="badge badge-success">Multi-stores</span>
                                @else
                                    <span class="badge badge-secondary">Single store</span>
                                @endif
                            </td>
                            <td>
                                @if ($tenant['is_active'])
                                    <span class="badge badge-success">Actif</span>
                                @else
                                    <span class="badge badge-warning">Inactif</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('system.tenants.edit', $tenant['code']) }}">
                                    Modifier
                                </a>
                                <a class="btn btn-secondary" href="{{ route('system.tenants.subscription', $tenant['code']) }}">
                                    Abonnement
                                </a>
                                <a class="btn btn-secondary" href="{{ route('system.tenants.settings', $tenant['code']) }}">
                                    Paramètres
                                </a>
                                <button class="btn btn-secondary" wire:click="delete({{ $tenant['id'] }})">
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
