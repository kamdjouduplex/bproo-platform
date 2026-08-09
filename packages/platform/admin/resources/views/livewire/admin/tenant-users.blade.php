<div class="page-body">
    @if ($limitExceeded)
        <div class="cc-card" style="margin-bottom:16px;padding:16px 18px;border-left:4px solid #dc2626;background:#fef2f2;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong style="color:#b91c1c;font-size:15px;">⚠ Plafond utilisateurs dépassé — action commerciale requise</strong>
                    <p style="margin:8px 0 0;color:#7f1d1d;line-height:1.45;">
                        <strong>{{ $tenant->name }}</strong> (<code>{{ $tenant->code }}</code>) a
                        <strong>{{ $activeCount }}</strong> utilisateur(s) actif(s)
                        pour un plafond de <strong>{{ $tenant->max_users }}</strong>
                        ({{ $totalCount }} compte(s) au total).
                        Interpellez le client pour régulariser la facturation (tarif / siège / mois),
                        augmentez le plafond, ou désactivez des comptes ci-dessous.
                    </p>
                </div>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.edit', $tenant) }}">Augmenter le plafond</a>
            </div>
        </div>
    @endif

    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Sièges actifs (facturables)</div>
            <div class="dashboard-kpi__value" @if($limitExceeded) style="color:#b91c1c;" @endif>
                {{ $activeCount }}{{ $tenant->hasUsersLimit() ? ' / '.$tenant->max_users : '' }}
            </div>
            <div class="dashboard-kpi__meta">{{ $totalCount }} compte(s) en base</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Statut quota</div>
            <div class="dashboard-kpi__value" style="font-size:1.15rem;{{ $limitExceeded ? 'color:#b91c1c;' : 'color:#15803d;' }}">
                {{ $limitExceeded ? 'Dépassé' : ($tenant->hasUsersLimit() ? 'OK' : 'Illimité') }}
            </div>
        </div>
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">
                Utilisateurs
                <span style="font-weight:500;color:#64748b;">· {{ $totalCount }}</span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="refreshMetrics">Actualiser</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.edit', $tenant) }}">Modifier plafond</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.show', $tenant) }}">Fiche entreprise</a>
            </div>
        </div>

        @if ($error)
            <div style="padding:16px;color:#b91c1c;">{{ $error }}</div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Créé</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" @if(isset($user->is_active) && !$user->is_active) style="opacity:.65;" @endif>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?? '—' }}</td>
                                <td>
                                    @isset($user->is_active)
                                        @if ($user->is_active)
                                            <span class="badge badge-success">Actif</span>
                                        @else
                                            <span class="badge badge-warning">Inactif</span>
                                        @endif
                                    @else
                                        —
                                    @endisset
                                </td>
                                <td>
                                    @if (!empty($user->created_at))
                                        {{ \Illuminate\Support\Carbon::parse($user->created_at)->format('d/m/Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    @if ($canToggleActive)
                                        <button
                                            type="button"
                                            class="btn btn-sm {{ !empty($user->is_active) ? 'btn-secondary' : 'btn-primary' }}"
                                            wire:click="toggleUserActive({{ $user->id }})"
                                            wire:confirm="{{ !empty($user->is_active) ? 'Désactiver cet utilisateur ? Il ne comptera plus dans les sièges facturables.' : 'Réactiver cet utilisateur ?' }}"
                                        >
                                            {{ !empty($user->is_active) ? 'Désactiver' : 'Activer' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Aucun utilisateur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
