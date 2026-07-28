<div class="page-body">
    <style>
        .loy-tabs { display:inline-flex; gap:4px; padding:4px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px; }
        .loy-tab { border:0; background:transparent; color:#64748b; font-size:13px; font-weight:600; padding:8px 18px; border-radius:9px; cursor:pointer; transition:all .15s ease; }
        .loy-tab:hover { color:#0f172a; }
        .loy-tab--active { background:#fff; color:#4f46e5; box-shadow:0 1px 2px rgba(15,23,42,.08); }

        .loy-actions { display:inline-flex; gap:6px; justify-content:flex-end; }
        .loy-btn {
            display:inline-flex; align-items:center; gap:6px;
            border:1px solid #e2e8f0; background:#fff; color:#475569;
            font-size:12px; font-weight:600; padding:6px 12px; border-radius:8px;
            cursor:pointer; transition:all .15s ease; white-space:nowrap;
        }
        .loy-btn svg { width:14px; height:14px; }
        .loy-btn:hover { border-color:#cbd5e1; background:#f8fafc; color:#0f172a; }
        .loy-btn--primary { border-color:#4f46e5; background:#4f46e5; color:#fff; }
        .loy-btn--primary:hover { background:#4338ca; border-color:#4338ca; color:#fff; }
        .loy-btn--danger { border-color:#fecaca; color:#dc2626; background:#fff; }
        .loy-btn--danger:hover { background:#fef2f2; border-color:#fca5a5; color:#b91c1c; }
        .loy-btn--ghost { border-color:transparent; background:transparent; color:#64748b; }
        .loy-btn--ghost:hover { background:#f1f5f9; color:#0f172a; }

        .loy-close {
            display:inline-flex; align-items:center; justify-content:center;
            width:32px; height:32px; border-radius:50%; border:1px solid #e2e8f0;
            background:#fff; color:#64748b; font-size:18px; line-height:1; cursor:pointer; transition:all .15s ease;
        }
        .loy-close:hover { background:#f1f5f9; color:#0f172a; }
    </style>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    @unless ($active)
        <div class="alert alert-warning" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <span>{{ __('Le programme de fidélité est désactivé.') }}</span>
            <a class="loy-btn loy-btn--primary" href="{{ route('tenant.pressing_settings.loyalty', ['tenant' => $tenantCode]) }}" style="text-decoration:none;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ __('Configurer') }}
            </a>
        </div>
    @endunless

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <div class="card" style="padding:14px 18px;flex:1;min-width:150px;">
            <div style="font-size:12px;color:#64748b;">{{ __('Membres actifs') }}</div>
            <div style="font-size:1.6rem;font-weight:700;">{{ $summary['members'] }}</div>
        </div>
        <div class="card" style="padding:14px 18px;flex:1;min-width:150px;">
            <div style="font-size:12px;color:#64748b;">{{ __('Bons disponibles') }}</div>
            <div style="font-size:1.6rem;font-weight:700;color:#16a34a;">{{ $summary['available_rewards'] }}</div>
        </div>
        <div class="card" style="padding:14px 18px;flex:1;min-width:150px;">
            <div style="font-size:12px;color:#64748b;">{{ __('Bons utilisés') }}</div>
            <div style="font-size:1.6rem;font-weight:700;">{{ $summary['used_rewards'] }}</div>
        </div>
    </div>

    <section class="card app-table-card">
        <div class="client-list-head">
            <div class="loy-tabs">
                <button type="button" class="loy-tab {{ $tab === 'clients' ? 'loy-tab--active' : '' }}" wire:click="setTab('clients')">{{ __('Clients') }}</button>
                <button type="button" class="loy-tab {{ $tab === 'rewards' ? 'loy-tab--active' : '' }}" wire:click="setTab('rewards')">{{ __('Récompenses') }}</button>
            </div>
        </div>

        <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap;">
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Nom, WhatsApp, code…') }}" style="flex:1;min-width:200px;">
            @if ($tab === 'clients')
                <select class="input" wire:model.live="agenceFilter" style="max-width:220px;">
                    <option value="">{{ __('Toutes agences') }}</option>
                    @foreach ($agences as $agence)
                        <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        @if ($tab === 'clients')
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Client') }}</th>
                            <th>{{ __('Agence') }}</th>
                            <th style="text-align:right;">{{ __('Commandes fidélité') }}</th>
                            <th style="text-align:right;">{{ __('Points') }}</th>
                            <th style="text-align:center;">{{ __('Vers récompense') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $client)
                            @php $progress = $threshold > 0 ? min(100, round(($client->loyalty_points % $threshold) / $threshold * 100)) : 0; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $client->full_name }}</strong>
                                    <div style="font-size:12px;color:#94a3b8;">{{ $client->whatsapp ?: $client->code }}</div>
                                </td>
                                <td>{{ $client->agence?->name }}</td>
                                <td style="text-align:right;">{{ $client->loyalty_orders_count }}</td>
                                <td style="text-align:right;font-weight:700;">{{ $client->loyalty_points }}</td>
                                <td style="min-width:140px;">
                                    <div style="background:#e2e8f0;border-radius:999px;height:8px;overflow:hidden;">
                                        <div style="width:{{ $progress }}%;height:8px;background:#6366f1;"></div>
                                    </div>
                                    <div style="font-size:11px;color:#94a3b8;text-align:center;margin-top:2px;">
                                        {{ $client->loyalty_points % $threshold }} / {{ $threshold }}
                                    </div>
                                </td>
                                <td>
                                    <div class="loy-actions">
                                        <button type="button" class="loy-btn" wire:click="showDetail({{ $client->id }})">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('Détail') }}
                                        </button>
                                        @if ($canAdjust)
                                            <button type="button" class="loy-btn" wire:click="openAdjust({{ $client->id }})">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                {{ __('Ajuster') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">{{ __('Aucun client.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="padding:12px 16px;">{{ $clients->links() }}</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Bon') }}</th>
                            <th>{{ __('Client') }}</th>
                            <th>{{ __('Récompense') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th>{{ __('Commande') }}</th>
                            <th>{{ __('Expire le') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rewards as $reward)
                            <tr>
                                <td style="font-family:monospace;">{{ $reward->code }}</td>
                                <td>{{ $reward->client?->full_name }}</td>
                                <td>{{ $reward->label() }}</td>
                                <td>
                                    @php
                                        $badge = match ($reward->status) {
                                            'available' => ['#dcfce7', '#15803d', __('Disponible')],
                                            'used' => ['#e0e7ff', '#4338ca', __('Utilisé')],
                                            'expired' => ['#fee2e2', '#b91c1c', __('Expiré')],
                                            default => ['#f1f5f9', '#64748b', __('Annulé')],
                                        };
                                    @endphp
                                    <span style="background:{{ $badge[0] }};color:{{ $badge[1] }};padding:2px 8px;border-radius:999px;font-size:12px;">{{ $badge[2] }}</span>
                                </td>
                                <td>{{ $reward->order?->number ?? '—' }}</td>
                                <td>{{ $reward->expires_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    <div class="loy-actions">
                                        @if ($canAdjust && $reward->status === 'available')
                                            <button type="button" class="loy-btn loy-btn--danger" wire:click="cancelReward({{ $reward->id }})" wire:confirm="{{ __('Annuler ce bon ?') }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                {{ __('Annuler') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ __('Aucune récompense.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="padding:12px 16px;">{{ $rewards->links() }}</div>
        @endif
    </section>

    @if ($detailClient)
        <div class="modal-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:50;padding:16px;" wire:click.self="closeDetail">
            <div class="card" style="max-width:640px;width:100%;max-height:85vh;overflow:auto;padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:start;gap:8px;">
                    <div>
                        <h3 style="margin:0;">{{ $detailClient->full_name }}</h3>
                        <p style="margin:4px 0 0;color:#64748b;font-size:13px;">
                            {{ __('Solde') }} : <strong>{{ $detailClient->loyalty_points }}</strong> {{ __('points') }} ·
                            {{ $detailClient->loyalty_orders_count }} {{ __('commandes') }}
                        </p>
                    </div>
                    <button type="button" class="loy-close" wire:click="closeDetail" aria-label="{{ __('Fermer') }}">×</button>
                </div>

                <h4 style="margin:16px 0 8px;font-size:.9rem;">{{ __('Récompenses') }}</h4>
                @forelse ($detailClient->loyaltyRewards as $r)
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px;">
                        <span style="font-family:monospace;">{{ $r->code }}</span>
                        <span>{{ $r->label() }}</span>
                        <span style="color:#94a3b8;">{{ $r->status }}</span>
                    </div>
                @empty
                    <p style="color:#94a3b8;font-size:13px;">{{ __('Aucune récompense.') }}</p>
                @endforelse

                <h4 style="margin:16px 0 8px;font-size:.9rem;">{{ __('Historique des points') }}</h4>
                @forelse ($detailClient->loyaltyEntries as $entry)
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px;">
                        <span>{{ $entry->reason }}</span>
                        <span style="font-weight:700;color:{{ $entry->points >= 0 ? '#16a34a' : '#dc2626' }};">
                            {{ $entry->points >= 0 ? '+' : '' }}{{ $entry->points }}
                        </span>
                        <span style="color:#94a3b8;">{{ $entry->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                @empty
                    <p style="color:#94a3b8;font-size:13px;">{{ __('Aucun mouvement.') }}</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($adjustClientId)
        <div class="modal-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:50;padding:16px;" wire:click.self="closeAdjust">
            <div class="card" style="max-width:420px;width:100%;padding:20px;">
                <h3 style="margin:0 0 12px;">{{ __('Ajuster les points') }}</h3>
                <div class="field">
                    <label class="field-label">{{ __('Points (+ / -)') }}</label>
                    <input class="input" type="number" wire:model="adjust_points" placeholder="ex: 5 ou -3">
                    @error('adjust_points')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
                </div>
                <div class="field" style="margin-top:10px;">
                    <label class="field-label">{{ __('Motif') }}</label>
                    <input class="input" type="text" wire:model="adjust_reason" maxlength="255" placeholder="{{ __('optionnel') }}">
                </div>
                <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="loy-btn loy-btn--ghost" wire:click="closeAdjust">{{ __('Annuler') }}</button>
                    <button type="button" class="loy-btn loy-btn--primary" wire:click="saveAdjust">{{ __('Valider') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
