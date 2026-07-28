<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Packages — modules par vendeur</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input class="input input-sm" placeholder="Rechercher un module..." wire:model.live.debounce.300ms="search" style="min-width:180px;">
                <select class="input input-sm" wire:model.live="tenantId" style="min-width:200px;">
                    <option value="">Sélectionner un vendeur</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->code }})</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-secondary" wire:click="syncModules">
                    Synchroniser les modules
                </button>
                <button type="button" class="btn btn-secondary" wire:click="clearStuckPending" title="Si Installer ne répond plus après une erreur">
                    Débloquer
                </button>
            </div>
        </div>

        @if (!$tenantId)
            <p class="card-body">Sélectionnez un vendeur pour installer ou désinstaller des modules.</p>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Description</th>
                            <th>Groupe</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modulesList as $module)
                            @php
                                $enabled = $enabledMap[$module['key']] ?? false;
                                $pending = $pendingMap[$module['key']] ?? false;
                            @endphp
                            <tr>
                                <td>{{ $module['label'] }}</td>
                                <td>{{ $module['description'] }}</td>
                                <td>{{ $module['group'] }}</td>
                                <td>
                                    @if ($pending)
                                        <span class="badge badge-warning">En cours…</span>
                                    @elseif ($module['core'])
                                        <span class="badge badge-success">Actif (core)</span>
                                    @elseif ($enabled)
                                        <span class="badge badge-success">Installé</span>
                                    @else
                                        <span class="badge" style="background:#e2e8f0;color:#64748b;">Non installé</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($module['core'])
                                        —
                                    @elseif ($pending)
                                        <span class="btn btn-secondary" disabled>En cours…</span>
                                    @elseif ($enabled)
                                        <button type="button" class="btn btn-secondary" wire:click="uninstall('{{ $module['key'] }}')">
                                            Désinstaller
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-primary" wire:click="install('{{ $module['key'] }}')" wire:loading.attr="disabled" wire:target="install('{{ $module['key'] }}')">
                                            <span wire:loading.remove wire:target="install('{{ $module['key'] }}')">Installer</span>
                                            <span wire:loading wire:target="install('{{ $module['key'] }}')">Installation…</span>
                                        </button>
                                        <button type="button" class="btn btn-secondary" wire:click="installForAllTenants('{{ $module['key'] }}')" title="Installer pour tous les vendeurs">
                                            Pour tous
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="card-body" style="margin-top:12px; font-size:12px; color:#64748b;">
                L’installation est immédiate. Choisissez le vendeur dans la liste ci-dessus avant d’installer, ou utilisez « Pour tous » pour activer le module pour tous les vendeurs.
            </p>
        @endif
    </section>
</div>
