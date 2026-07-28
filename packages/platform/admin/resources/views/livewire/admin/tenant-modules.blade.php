<div class="page-body">
    <div class="card">
        <div class="form-grid">
            <label class="field-label" for="tenant-select">Vendeur</label>
            <select id="tenant-select" class="input" wire:model.live="tenantId">
                <option value="">Sélectionner un vendeur</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant['id'] }}">
                        {{ $tenant['name'] }} ({{ $tenant['code'] }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Modules disponibles</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Description</th>
                        <th>Statut</th>
                        <th>Mise a jour</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $module)
                        @php
                            $enabled = $states[$module['id']] ?? false;
                        @endphp
                        <tr>
                            <td>{{ $module['label'] }}</td>
                            <td>{{ $module['description'] }}</td>
                            <td>
                                @if ($enabled)
                                    <span class="badge badge-success">Actif</span>
                                @else
                                    <span class="badge badge-warning">Désactivé</span>
                                @endif
                            </td>
                            <td>{{ $updatedAt[$module['id']] ?? '-' }}</td>
                            <td>
                                <button class="btn btn-secondary" wire:click="toggle({{ $module['id'] }})">
                                    {{ $enabled ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
