<div class="page-body">
    @if ($tenant->users_limit_exceeded_at)
        <div class="cc-card" style="margin-bottom:16px;padding:14px 16px;border-left:4px solid #dc2626;background:#fef2f2;">
            <strong style="color:#b91c1c;">Plafond dépassé</strong>
            <p style="margin:6px 0 0;color:#7f1d1d;">
                {{ $users->count() }} utilisateur(s) pour un plafond de {{ $tenant->max_users }}.
            </p>
        </div>
    @endif

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">
                Utilisateurs
                <span style="font-weight:500;color:#64748b;">
                    · {{ $users->count() }}{{ $tenant->hasUsersLimit() ? ' / '.$tenant->max_users : '' }}
                </span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="refreshMetrics">Actualiser compteur</button>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
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
                            </tr>
                        @empty
                            <tr><td colspan="6">Aucun utilisateur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
