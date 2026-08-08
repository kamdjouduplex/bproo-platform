<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Clients (entreprises)</div>
            <div class="dashboard-kpi__value">{{ $kpis['total'] }}</div>
            <div class="dashboard-kpi__meta">{{ $kpis['active'] }} active{{ $kpis['active'] !== 1 ? 's' : '' }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Avec abo. actif</div>
            <div class="dashboard-kpi__value">{{ $kpis['with_sub'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Venus du CRM</div>
            <div class="dashboard-kpi__value">{{ $kpis['from_crm'] }}</div>
            <div class="dashboard-kpi__meta">prospects convertis</div>
        </div>
    </section>

    @unless($embedded ?? false)
    <section class="cc-card" style="margin-bottom:14px;border-color:#bbf7d0;background:#f0fdf4;">
        <div class="cc-card__body" style="font-size:13px;color:#166534;line-height:1.45;">
            <strong>Clients = Companies.</strong>
            Chaque client Bproo est une entreprise (tenant) sur la plateforme. Pas de double saisie :
            Prospect → Opportunité → conversion → Client. L’écran Organisation → Companies reste la vue ops (provisionnement, modules).
        </div>
    </section>
    @endunless

    <section class="cc-card app-table-card">
        <div class="table-toolbar" style="padding:14px 16px;">
            <div class="table-title">Comptes clients</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants') }}">Vue ops Companies</a>
                <a class="btn btn-primary btn-sm" href="{{ route('system.prospects.create') }}">Nouveau via CRM</a>
            </div>
        </div>
        <div class="form-grid" style="padding:0 16px 12px;margin:0;">
            <div class="field">
                <input class="input" type="search" placeholder="Nom, code, contact…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="product">
                    <option value="">Toutes apps</option>
                    @foreach ($productTypes as $key => $cfg)
                        <option value="{{ $key }}">{{ $cfg['label'] ?? $key }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="status">
                    <option value="">Tous abonnements</option>
                    <option value="active_sub">Abo. actif</option>
                    <option value="suspended">Abo. suspendu</option>
                    <option value="none">Sans abonnement</option>
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="active">
                    <option value="">Actifs & inactifs</option>
                    <option value="1">Entreprise active</option>
                    <option value="0">Entreprise inactive</option>
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>App</th>
                        <th>Abonnement</th>
                        <th>Origine CRM</th>
                        <th>Total payé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $sub = $subs->get($client->id);
                            $origin = $origins->get($client->id);
                            $contact = trim(($client->contact_key_first_name ?? '').' '.($client->contact_key_last_name ?? ''));
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $client->name }}</strong>
                                <div style="font-size:11px;color:#64748b;">
                                    <code>{{ $client->code }}</code>
                                    ·
                                    @if ($client->is_active)
                                        <span style="color:#15803d;">active</span>
                                    @else
                                        <span style="color:#b45309;">inactive</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                {{ $contact !== '' ? $contact : '—' }}
                                <div style="font-size:11px;color:#64748b;">{{ $client->contact_key_phone ?: '—' }}</div>
                            </td>
                            <td><span class="badge badge-secondary">{{ $client->type_label }}</span></td>
                            <td>
                                @if ($sub)
                                    <span class="badge badge-{{ $sub->status_color }}">{{ $statusLabels[$sub->status] ?? $sub->status }}</span>
                                    <div style="font-size:11px;color:#64748b;">{{ $sub->plan?->name }} · fin {{ $sub->current_period_end?->format('d/m/Y') }}</div>
                                @else
                                    <span class="badge badge-warning">Aucun</span>
                                @endif
                            </td>
                            <td>
                                @if ($origin)
                                    <a href="{{ route('system.prospects.edit', $origin) }}">{{ $origin->company_name }}</a>
                                    <div style="font-size:11px;color:#64748b;">{{ \App\Models\PlatformProspect::sources()[$origin->source] ?? $origin->source }}</div>
                                @else
                                    <span style="color:#94a3b8;">Créé manuellement</span>
                                @endif
                            </td>
                            <td><strong>{{ fmt_money($client->total_paid ?? 0) }}</strong></td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('system.tenants.show', $client) }}">Fiche</a>
                                <a class="btn btn-primary btn-sm" href="{{ route('system.tenants.subscription', $client) }}">Facturer</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="stock-empty">Aucun client.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
