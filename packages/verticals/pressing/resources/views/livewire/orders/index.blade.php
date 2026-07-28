@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code');
    $statusLabels = [
        'all' => 'Toutes',
        'open' => 'Ouvertes',
        'ready' => 'Prêtes',
        'delivered' => 'Livrées',
    ];
@endphp

<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">Aujourd'hui</div>
            <div style="font-size:1.25rem;font-weight:700;color:#0f172a;margin-top:4px;">{{ $todayOrders }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Réceptions du jour</div>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">CA du jour</div>
            <div style="font-size:1.25rem;font-weight:700;color:#0f172a;margin-top:4px;">{{ number_format($todayRevenue, 0, ',', ' ') }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">
                FCFA · encaissé <strong style="color:#15803d;font-weight:700;">{{ number_format($todayCollected, 0, ',', ' ') }}</strong>
            </div>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">En cours</div>
            <div style="font-size:1.25rem;font-weight:700;color:#b45309;margin-top:4px;">{{ (int) ($statusCounts['open'] ?? 0) }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Commandes ouvertes</div>
        </div>
        <div class="card" style="padding:14px 16px;margin:0;">
            <div style="font-size:12px;color:#64748b;font-weight:600;">Reste à percevoir</div>
            <div style="font-size:1.25rem;font-weight:700;color:#c2410c;margin-top:4px;">{{ number_format($filteredBalance, 0, ',', ' ') }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Sur le filtre actuel</div>
        </div>
    </div>

    <section class="card app-table-card client-list-card">
        <div class="client-list-head">
            <div>
                <h2 class="client-list-head__title">Commandes</h2>
                @if ($lockedAgence)
                    <p style="margin:4px 0 0;font-size:12px;color:#64748b;">Agence <strong>{{ $lockedAgence->name }}</strong></p>
                @endif
            </div>
            <div class="client-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_workflow.index', ['tenant' => $tenantCode]) }}">Workflow</a>
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.pressing_orders.create', ['tenant' => $tenantCode]) }}">Nouvelle réception</a>
                @endif
            </div>
        </div>

        <div class="client-filter-bar">
            <div class="client-filter-bar__search">
                <input class="input input-sm client-filter-bar__search-input"
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="N° commande, client, WhatsApp…"
                    aria-label="Rechercher une commande">
            </div>
            <div class="client-filter-bar__tools">
                @if ($canViewAllAgences)
                    <select class="input input-sm" wire:model.live="agenceFilter" aria-label="Agence" style="max-width:180px;">
                        <option value="">Toutes agences</option>
                        @foreach ($agences as $agence)
                            <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($search !== '' || $statusFilter !== 'all' || ($canViewAllAgences && $agenceFilter))
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinit.</button>
                @endif
                <label class="client-filter-bar__per-page">
                    <span class="sr-only">Par page</span>
                    <select class="input input-sm" wire:model.live="perPage">
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="client-status-pills" role="group" aria-label="Filtrer par statut">
            @foreach ($statusLabels as $value => $label)
                <button type="button"
                    class="client-status-pill {{ $statusFilter === $value ? 'client-status-pill--active' : '' }}"
                    wire:click="$set('statusFilter', '{{ $value }}')">
                    {{ $label }}
                    @if ($value !== 'all' && isset($statusCounts[$value]))
                        <span style="opacity:.75;font-weight:700;">· {{ $statusCounts[$value] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Reçue le</th>
                        <th>Client</th>
                        @if ($canViewAllAgences)
                            <th>Agence</th>
                        @endif
                        <th>Étape</th>
                        <th>Articles</th>
                        <th>Total</th>
                        <th>Avance</th>
                        <th>Reste</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td><strong>{{ $order->number }}</strong></td>
                            <td style="white-space:nowrap;font-size:12px;color:#64748b;">{{ $order->received_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <div>{{ $order->client?->full_name }}</div>
                                @if ($order->client?->whatsapp)
                                    <div style="font-size:11px;color:#94a3b8;">{{ $order->client->whatsapp }}</div>
                                @endif
                            </td>
                            @if ($canViewAllAgences)
                                <td>{{ $order->agence?->name }}</td>
                            @endif
                            <td>
                                @if ($order->currentStage)
                                    <span style="display:inline-block;padding:2px 10px;border-radius:999px;background:{{ $order->currentStage->color }}22;color:{{ $order->currentStage->color }};font-size:11px;font-weight:600;">
                                        {{ $order->currentStage->name }}
                                    </span>
                                @else
                                    <span class="badge badge-neutral">—</span>
                                @endif
                            </td>
                            <td>{{ $order->items_count }}</td>
                            <td style="font-weight:600;white-space:nowrap;">{{ number_format((float) $order->total, 0, ',', ' ') }}</td>
                            <td>
                                @if ((float) $order->amount_paid > 0)
                                    <span style="color:#15803d;font-weight:600;">{{ number_format((float) $order->amount_paid, 0, ',', ' ') }}</span>
                                @else
                                    <span style="color:#94a3b8;">0</span>
                                @endif
                            </td>
                            <td>
                                @if ((float) $order->balance > 0)
                                    <span style="color:#c2410c;font-weight:600;">{{ number_format((float) $order->balance, 0, ',', ' ') }}</span>
                                @else
                                    <span style="color:#15803d;font-weight:600;">Soldé</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                @if ($canUpdate && $order->status !== 'delivered')
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_orders.edit', ['pressingOrder' => $order->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @endif
                                @if ($canSort && ! $order->isSortingCompleted())
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.pressing_orders.tri', ['pressingOrder' => $order->id, 'tenant' => $tenantCode]) }}">Constituer</a>
                                @endif
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_orders.print', ['pressingOrder' => $order->id, 'tenant' => $tenantCode, 'type' => 'deposit']) }}" onclick="event.preventDefault(); window.open(this.href, 'pressing-print', 'width=960,height=720');">Reçu</a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_orders.print', ['pressingOrder' => $order->id, 'tenant' => $tenantCode, 'type' => 'label']) }}" onclick="event.preventDefault(); window.open(this.href, 'pressing-print', 'width=960,height=720');">QR</a>
                                @if ($canPay)
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="openPayment({{ $order->id }})">
                                        {{ (float) $order->balance > 0 ? 'Payer' : 'Paiements' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewAllAgences ? 10 : 9 }}" style="text-align:center;padding:32px 12px;color:#64748b;">
                                Aucune commande trouvée.
                                @if ($canCreate)
                                    <div style="margin-top:12px;">
                                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.pressing_orders.create', ['tenant' => $tenantCode]) }}">Nouvelle réception</a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;">{{ $orders->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>

    @if ($showPayment)
        <div class="modal-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:80;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closePayment">
            <div class="card" style="width:100%;max-width:520px;padding:20px;margin:0;" role="dialog" aria-modal="true">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;">
                    <div>
                        <h3 style="margin:0;font-size:1.05rem;">Paiements</h3>
                        <p style="margin:4px 0 0;color:#64748b;font-size:13px;">{{ $paymentOrderNumber }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closePayment" aria-label="Fermer">×</button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;padding:12px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                    <div><div style="font-size:11px;color:#64748b;">Total</div><strong>{{ number_format((float) $paymentOrderTotal, 0, ',', ' ') }}</strong></div>
                    <div><div style="font-size:11px;color:#64748b;">Payé</div><strong style="color:#15803d;">{{ number_format((float) $paymentOrderPaid, 0, ',', ' ') }}</strong></div>
                    <div><div style="font-size:11px;color:#64748b;">Reste</div><strong style="color:#c2410c;">{{ number_format((float) $paymentOrderBalance, 0, ',', ' ') }}</strong></div>
                </div>

                @if (count($existingPayments) > 0)
                    <div style="margin-bottom:14px;">
                        <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;">Historique</div>
                        <div style="max-height:160px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">
                            @foreach ($existingPayments as $payment)
                                <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 10px;border-bottom:1px solid #f1f5f9;font-size:12px;">
                                    <span>{{ $payment['paid_at'] }} · {{ $payment['method'] }}</span>
                                    <strong>{{ number_format((float) $payment['amount'], 0, ',', ' ') }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ((float) $paymentOrderBalance > 0)
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">Mode</label>
                            <select class="input" wire:model="payment_method">
                                @foreach ($paymentMethods as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label">Montant</label>
                            <input class="input" type="number" step="0.01" wire:model="payment_amount">
                            @error('payment_amount')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="grid-column:1/-1;">
                            <label class="field-label">Référence</label>
                            <input class="input" wire:model="payment_reference" placeholder="Optionnel">
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                        <button type="button" class="btn btn-secondary" wire:click="closePayment">Annuler</button>
                        <button type="button" class="btn btn-primary" wire:click="savePayment" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="savePayment">Enregistrer</span>
                            <span wire:loading wire:target="savePayment">En cours…</span>
                        </button>
                    </div>
                @else
                    <p style="color:#15803d;margin:0;">Commande entièrement soldée.</p>
                    <div style="margin-top:14px;text-align:right;">
                        <button type="button" class="btn btn-secondary" wire:click="closePayment">Fermer</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
