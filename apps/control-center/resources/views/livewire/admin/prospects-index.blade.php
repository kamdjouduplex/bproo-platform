<div class="page-body">
    <section class="dashboard-kpis" style="margin-bottom:16px;">
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Nouveaux</div>
            <div class="dashboard-kpi__value">{{ $kpis['leads'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Suivis dus</div>
            <div class="dashboard-kpi__value">{{ $kpis['follow_ups'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">Sans commercial</div>
            <div class="dashboard-kpi__value">{{ $kpis['unassigned'] }}</div>
        </div>
        <div class="dashboard-kpi">
            <div class="dashboard-kpi__label">À convertir</div>
            <div class="dashboard-kpi__value">{{ $kpis['won_pending'] }}</div>
            @if ($kpis['won_pending'] > 0)
                <div class="dashboard-kpi__meta"><a href="{{ route('system.opportunities') }}">Voir pipeline →</a></div>
            @endif
        </div>
    </section>

    <section class="cc-card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Prospects <span style="font-weight:500;color:#64748b;">· {{ $prospects->total() }}</span></div>
            <a class="btn btn-primary" href="{{ route('system.prospects.create') }}">Nouveau</a>
        </div>
        <div class="form-grid">
            <div class="field">
                <input class="input" type="search" placeholder="Rechercher…" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <select class="input" wire:model.live="stage">
                    <option value="">Toutes étapes</option>
                    @foreach ($stages as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
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
                <select class="input" wire:model.live="country">
                    <option value="">Tous pays</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="city">
                    <option value="">Toutes villes</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select class="input" wire:model.live="owner">
                    <option value="">Tous commerciaux</option>
                    <option value="none">Non affectés</option>
                    @foreach ($salespeople as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Lieu</th>
                        <th>App</th>
                        <th>Étape</th>
                        <th>Commercial</th>
                        <th>Suivi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prospects as $prospect)
                        <tr wire:key="prospect-{{ $prospect->id }}">
                            <td>
                                <a href="{{ route('system.prospects.edit', $prospect) }}" style="font-weight:600;color:inherit;text-decoration:none;">
                                    {{ $prospect->company_name }}
                                </a>
                                @if ($prospect->convertedTenant)
                                    <div style="font-size:12px;color:#64748b;">
                                        → <a href="{{ route('system.tenants.show', $prospect->convertedTenant) }}">{{ $prospect->convertedTenant->code }}</a>
                                    </div>
                                @endif
                            </td>
                            <td style="font-size:13px;color:#475569;">
                                {{ $prospect->city ?: '—' }}
                                @if ($prospect->country)
                                    <div style="font-size:11px;color:#94a3b8;">{{ $prospect->country }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-secondary">{{ $prospect->productLabel() }}</span></td>
                            <td><span class="badge badge-secondary">{{ $prospect->stageLabel() }}</span></td>
                            <td style="min-width:140px;">
                                @if (!$prospect->converted_tenant_id)
                                    <select class="input input-sm" wire:change="assignOwner({{ $prospect->id }}, $event.target.value)">
                                        <option value="">—</option>
                                        @foreach ($salespeople as $user)
                                            <option value="{{ $user->id }}" @selected((int) $prospect->owner_user_id === (int) $user->id)>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $prospect->owner?->name ?: '—' }}
                                @endif
                            </td>
                            <td>
                                @if ($prospect->next_follow_up_at)
                                    <span class="{{ $prospect->next_follow_up_at->isPast() && !$prospect->converted_tenant_id ? 'cc-opp-card__late' : '' }}">
                                        {{ $prospect->next_follow_up_at->format('d/m/Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-primary btn-sm" href="{{ route('system.prospects.edit', $prospect) }}">Fiche</a>
                                @if (!$prospect->converted_tenant_id && $prospect->stage === 'lead')
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="promoteToOpportunity({{ $prospect->id }})">→ Opp.</button>
                                @endif
                                @if (!$prospect->converted_tenant_id && $prospect->stage === 'won')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('system.opportunities') }}">Convertir</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="stock-empty">
                                Aucun prospect.
                                <a href="{{ route('system.prospects.create') }}">Créer</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($prospects->hasPages())
            <div class="table-pagination" style="padding:12px 20px;">
                {{ $prospects->links() }}
            </div>
        @endif
    </section>
</div>
