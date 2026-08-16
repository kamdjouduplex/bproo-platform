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
                                @if (in_array($status['provisioning_status'] ?? '', ['failed', 'pending', 'provisioning'], true))
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        wire:click="openRetry('{{ $status['code'] }}')"
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

    @if ($retryCode)
        <div
            class="modal-backdrop"
            style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:80;display:flex;align-items:center;justify-content:center;padding:16px;"
            wire:click.self="cancelRetry"
            wire:key="retry-provision-modal"
        >
            <div
                class="card"
                style="width:100%;max-width:480px;padding:20px;margin:0;"
                role="dialog"
                aria-modal="true"
                aria-labelledby="retry-provision-title"
            >
                <div class="table-title" id="retry-provision-title" style="margin-bottom: 8px;">
                    Relancer le provisionnement
                </div>
                <p style="margin: 0 0 14px; color: #555; font-size: 14px;">
                    Compte admin pour <strong>{{ $retryCode }}</strong>
                    (requis si la base n’a pas encore d’utilisateur).
                </p>

                <div style="display:grid; gap: 12px;">
                    <div>
                        <label class="label">Nom admin</label>
                        <input class="input" type="text" wire:model="admin_name" autocomplete="off" autofocus>
                        @error('admin_name') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Email admin</label>
                        <input class="input" type="email" wire:model="admin_email" autocomplete="off">
                        @error('admin_email') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Mot de passe admin</label>
                        <input class="input" type="password" wire:model="admin_password" autocomplete="new-password">
                        @error('admin_password') <span class="text-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="display:flex; gap: 10px; justify-content:flex-end; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" wire:click="cancelRetry">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" wire:click="retryProvisioning" wire:loading.attr="disabled">
                        Confirmer la relance
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
