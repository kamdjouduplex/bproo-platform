<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Historique des modules</div>
            <div style="display:flex; gap:8px; align-items:center;">
                <input class="input input-sm" placeholder="Recherche module..." wire:model.debounce.400ms="search">
                <select class="input input-sm" wire:model="tenantId">
                    <option value="">Tous vendeurs</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->code }})</option>
                    @endforeach
                </select>
                <select class="input input-sm" wire:model="moduleKey">
                    <option value="">Tous modules</option>
                    @foreach (collect(config('modules'))->except('core_migration_tags')->filter(fn ($m) => is_array($m) && isset($m['label'])) as $key => $module)
                        <option value="{{ $key }}">{{ $module['label'] }}</option>
                    @endforeach
                </select>
                <select class="input input-sm" wire:model="action">
                    <option value="">Toutes actions</option>
                    <option value="install">Activation</option>
                    <option value="uninstall">Désactivation</option>
                </select>
                <select class="input input-sm" wire:model="perPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <a class="btn btn-secondary" href="{{ route('system.module.events.export', request()->query()) }}">
                    Exporter
                </a>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendeur</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Par</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>{{ $event->created_at }}</td>
                            <td>{{ $event->tenant?->name ?? '-' }}</td>
                            <td>{{ $event->module_key }}</td>
                            <td>{{ $event->action }}</td>
                            <td>{{ $event->performer?->email ?? '-' }}</td>
                        </tr>
                    @endforeach
                    @if ($events->count() === 0)
                        <tr>
                            <td colspan="5">Aucun événement.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $events->links() }}
        </div>
    </section>
</div>
