@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $contact = $provider->primaryContact;

    $purchaseStatusLabel = function ($status) {
        return [
            'draft' => 'Brouillon',
            'confirmed' => 'Confirmée',
            'partial' => 'Réception partielle',
            'sent' => 'Réception partielle',
            'received' => 'Réceptionnée',
            'cancelled' => 'Annulée',
        ][$status] ?? ucfirst((string) $status);
    };
    $purchaseStatusBadge = function ($status) {
        return [
            'draft' => 'badge-secondary',
            'confirmed' => 'badge-info',
            'partial' => 'badge-warning',
            'sent' => 'badge-warning',
            'received' => 'badge-success',
            'cancelled' => 'badge-error',
        ][$status] ?? 'badge-secondary';
    };
    $canViewPurchase = \Illuminate\Support\Facades\Route::has('tenant.purchases.show');
@endphp

<div class="page-body">
    <section class="card" style="margin-bottom: 16px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
            <div>
                <h2 class="card-title" style="margin:0;">{{ $provider->name }}</h2>
                <div style="color:#6b7280; margin-top:4px;">Code : <strong>{{ $provider->code }}</strong></div>
            </div>
            <div>
                @if ($provider->is_active)
                    <span class="badge badge-success">Actif</span>
                @else
                    <span class="badge badge-warning">Inactif</span>
                @endif
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px;">
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Téléphone</div>
                <strong>{{ $provider->phone ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Email</div>
                <strong>{{ $provider->email ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">NIF / Tax ID</div>
                <strong>{{ $provider->tax_id ?? '—' }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Mode de paiement</div>
                <strong>{{ \InovCom\Providers\Models\Provider::paymentMethodLabel($provider->payment_method) }}</strong>
            </div>
            <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Délai de paiement</div>
                <strong>
                    @if ($provider->paymentTerm)
                        {{ $provider->paymentTerm->name }} ({{ $provider->paymentTerm->days }} jours)
                    @else
                        —
                    @endif
                </strong>
            </div>
        </div>

        <div style="margin-top:16px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">Adresse</div>
                <div>{{ $provider->address ?? '—' }}</div>
                <div style="margin-top:4px;">{{ $provider->city ?? '' }}@if($provider->city && $provider->country), @endif{{ $provider->country ?? '' }}</div>
            </div>
            @if ($provider->notes)
            <div>
                <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">Notes</div>
                <div style="white-space:pre-wrap;">{{ $provider->notes }}</div>
            </div>
            @endif
        </div>
    </section>

    @if ($purchasesAvailable)
    <section class="card" style="margin-bottom: 16px;">
        <h3 class="card-title" style="margin-bottom:4px;">Récap des achats</h3>
        <p style="font-size:12px; color:#6b7280; margin:0 0 14px;">Synthèse de toutes les commandes d'achat passées chez ce fournisseur.</p>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:12px;">
            <div style="padding:14px; border:1px solid #e5e7eb; border-left:4px solid #2563eb; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Total des achats</div>
                <div style="font-size:20px; font-weight:700;">{{ fmt_money($purchaseStats['total_amount']) }} <small>FCFA</small></div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">{{ $purchaseStats['count'] }} commande(s) au total</div>
            </div>
            <div style="padding:14px; border:1px solid #e5e7eb; border-left:4px solid #16a34a; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Réceptionnées</div>
                <div style="font-size:20px; font-weight:700;">{{ fmt_money($purchaseStats['received_amount']) }} <small>FCFA</small></div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">{{ $purchaseStats['received_count'] }} commande(s)</div>
            </div>
            <div style="padding:14px; border:1px solid #e5e7eb; border-left:4px solid #d97706; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">En cours</div>
                <div style="font-size:20px; font-weight:700;">{{ fmt_money($purchaseStats['open_amount']) }} <small>FCFA</small></div>
                <div style="font-size:12px; color:#9ca3af; margin-top:4px;">{{ $purchaseStats['open_count'] }} commande(s)</div>
            </div>
            <div style="padding:14px; border:1px solid #e5e7eb; border-left:4px solid #6b7280; border-radius:8px;">
                <div style="font-size:12px; color:#6b7280;">Dernière commande</div>
                <div style="font-size:18px; font-weight:700;">
                    {{ $purchaseStats['last_order_date'] ? \Illuminate\Support\Carbon::parse($purchaseStats['last_order_date'])->format('d/m/Y') : '—' }}
                </div>
                @if ($purchaseStats['cancelled_count'] > 0)
                    <div style="font-size:12px; color:#9ca3af; margin-top:4px;">{{ $purchaseStats['cancelled_count'] }} annulée(s)</div>
                @endif
            </div>
        </div>

        <h3 class="card-title" style="margin:20px 0 12px;">Historique des achats</h3>
        @if ($purchases->isEmpty())
            <div style="padding:16px; background:#f9fafb; border:1px dashed #e5e7eb; border-radius:8px; color:#6b7280;">
                Aucune commande d'achat enregistrée pour ce fournisseur.
            </div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N° commande</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th style="text-align:right;">Réception</th>
                            <th style="text-align:right;">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $order)
                            <tr>
                                <td><strong>{{ $order->order_number }}</strong></td>
                                <td>{{ $order->order_date?->format('d/m/Y') ?? '—' }}</td>
                                <td><span class="badge {{ $purchaseStatusBadge($order->status) }}">{{ $purchaseStatusLabel($order->status) }}</span></td>
                                <td style="text-align:right;">{{ fmt_num($order->reception_percent) }}%</td>
                                <td style="text-align:right;">{{ fmt_money($order->total) }}</td>
                                <td style="text-align:right;">
                                    @if ($canViewPurchase)
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.purchases.show', [$order->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @endif

    @if ($contact)
    <section class="card" style="margin-bottom: 16px;">
        <h3 class="card-title" style="margin-bottom:12px;">Contact principal</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">
            <div>
                <div style="font-size:12px; color:#6b7280;">Nom</div>
                <strong>{{ $contact->name }}</strong>
            </div>
            <div>
                <div style="font-size:12px; color:#6b7280;">Téléphone</div>
                <strong>{{ $contact->phone ?? '—' }}</strong>
            </div>
            <div>
                <div style="font-size:12px; color:#6b7280;">Email</div>
                <strong>{{ $contact->email ?? '—' }}</strong>
            </div>
            <div>
                <div style="font-size:12px; color:#6b7280;">Fonction</div>
                <strong>{{ $contact->position ?? '—' }}</strong>
            </div>
        </div>
    </section>
    @endif

    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('tenant.providers.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
        <a class="btn btn-primary" href="{{ route('tenant.providers.edit', [$provider->id, 'tenant' => $tenantCode]) }}">Modifier</a>
    </div>
</div>
