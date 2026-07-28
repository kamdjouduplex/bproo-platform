<div class="page-body">
    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <style>
        .deliv-scope {
            display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px;
            padding:4px; background:#f1f5f9; border-radius:12px; width:fit-content; max-width:100%;
        }
        .deliv-scope__btn {
            border:0; background:transparent; border-radius:10px;
            padding:10px 16px; font-size:13px; font-weight:600; cursor:pointer; color:#64748b;
            display:inline-flex; align-items:center; gap:8px;
        }
        .deliv-scope__btn--active { background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(15,23,42,.08); }
        .deliv-scope__btn--waiting.deliv-scope__btn--active {
            background: linear-gradient(180deg, #ecfdf5, #fff);
            color: #0f766e;
            box-shadow: 0 0 0 1px #99f6e4, 0 2px 8px rgba(63,167,150,.18);
        }
        .deliv-scope__count {
            font-size:11px; font-weight:700; min-width:22px; height:22px; padding:0 7px;
            border-radius:999px; background:#e2e8f0; color:#475569;
            display:inline-flex; align-items:center; justify-content:center;
        }
        .deliv-scope__btn--active .deliv-scope__count { background:#dbeafe; color:#1d4ed8; }
        .deliv-scope__btn--waiting .deliv-scope__count {
            background: #ccfbf1; color: #0f766e;
        }
        .deliv-scope__btn--waiting.deliv-scope__btn--active .deliv-scope__count {
            background: #3fa796; color: #fff;
            min-width: 26px; height: 26px; font-size: 12px;
        }
        .deliv-scope__btn--waiting.has-pending:not(.deliv-scope__btn--active) {
            color: #0f766e;
        }
        .deliv-banner {
            display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
            margin-bottom: 14px; padding: 12px 16px;
            border-radius: 12px; border: 1px solid #99f6e4;
            background: linear-gradient(90deg, #ecfdf5, #f0fdfa);
        }
        .deliv-banner__title { margin: 0; font-size: 14px; font-weight: 700; color: #0f766e; }
        .deliv-banner__text { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .deliv-banner__count {
            font-size: 1.35rem; font-weight: 800; color: #0f766e; line-height: 1;
        }
        .app-table-card--waiting {
            border-color: #99f6e4;
            box-shadow: 0 0 0 1px rgba(63,167,150,.12), 0 8px 24px rgba(15,23,42,.04);
        }
        .deliv-types { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .deliv-type {
            border:1px solid #e2e8f0; background:#fff; border-radius:999px;
            padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; color:#64748b;
        }
        .deliv-type--active { border-color:#2563eb; background:#eff6ff; color:#1d4ed8; }
        .deliv-settle {
            margin-top:16px; padding:16px; border:1px solid #fdba74; border-radius:14px;
            background:linear-gradient(180deg,#fff7ed,#fff);
        }
        .pay-badge {
            display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;
        }
        .pay-badge--ok { background:#dcfce7; color:#15803d; }
        .pay-badge--due { background:#ffedd5; color:#c2410c; }
        .pay-badge--credit { background:#dbeafe; color:#1d4ed8; }
        .pay-badge--pending { background:#fef3c7; color:#b45309; }
        .deliv-meta { font-size:12px; color:#64748b; }
        .deliv-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 80;
            background: rgba(15, 23, 42, 0.48);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .deliv-modal {
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow: auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
            display: flex;
            flex-direction: column;
        }
        .deliv-modal--wide { max-width: 640px; }
        .deliv-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 18px 20px 12px;
            border-bottom: 1px solid #f1f5f9;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }
        .deliv-modal__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .deliv-modal__hint { margin: 4px 0 0; font-size: 12px; color: #64748b; line-height: 1.4; }
        .deliv-modal__close {
            width: 32px; height: 32px; border-radius: 50%;
            border: 1px solid #e2e8f0; background: #fff; color: #64748b;
            font-size: 20px; line-height: 1; cursor: pointer; flex-shrink: 0;
        }
        .deliv-modal__close:hover { background: #f1f5f9; color: #0f172a; }
        .deliv-modal__body { padding: 16px 20px; }
        .deliv-modal__foot {
            display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
            padding: 12px 20px 18px; border-top: 1px solid #f1f5f9;
            position: sticky; bottom: 0; background: #fff;
        }
        .deliv-modal .deliv-consumable {
            display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
            padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
        }
    </style>

    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 class="client-list-head__title" style="margin:0;">{{ __('Livraisons') }}</h2>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                @if ($listScope === 'done')
                    {{ __('Historique des remises effectuées') }}
                @else
                    {{ __('Remises à traiter — solde ou crédit validé requis') }}
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.pressing_fin_production.index', ['tenant' => $tenantCode]) }}">{{ __('Fin de production') }}</a>
            @if ($canCreate)
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Planifier') }}</button>
            @endif
        </div>
    </div>

    @if (($counts['waiting'] ?? 0) > 0 && $listScope !== 'waiting')
        <div class="deliv-banner">
            <div>
                <p class="deliv-banner__title">{{ __(':n commande(s) en attente de remise', ['n' => $counts['waiting']]) }}</p>
                <p class="deliv-banner__text">{{ __('Priorisez les retraits et livraisons à traiter.') }}</p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="deliv-banner__count">{{ $counts['waiting'] }}</span>
                <button type="button" class="btn btn-primary btn-sm" wire:click="setListScope('waiting')">
                    {{ __('Voir les en attente') }}
                </button>
            </div>
        </div>
    @endif

    <div class="deliv-scope">
        <button type="button"
                class="deliv-scope__btn deliv-scope__btn--waiting {{ $listScope === 'waiting' ? 'deliv-scope__btn--active' : '' }} {{ ($counts['waiting'] ?? 0) > 0 ? 'has-pending' : '' }}"
                wire:click="setListScope('waiting')">
            {{ __('En attente') }}
            <span class="deliv-scope__count">{{ $counts['waiting'] }}</span>
        </button>
        <button type="button"
                class="deliv-scope__btn {{ $listScope === 'done' ? 'deliv-scope__btn--active' : '' }}"
                wire:click="setListScope('done')">
            {{ __('Effectuées') }}
            <span class="deliv-scope__count">{{ $counts['done'] }}</span>
        </button>
    </div>

    <div class="deliv-types">
        <button type="button" class="deliv-type {{ $typeTab === 'all' ? 'deliv-type--active' : '' }}" wire:click="$set('typeTab', 'all')">{{ __('Tous types') }}</button>
        <button type="button" class="deliv-type {{ $typeTab === 'agence' ? 'deliv-type--active' : '' }}" wire:click="$set('typeTab', 'agence')">
            {{ __('Retrait agence') }}
            @if ($listScope === 'waiting' && $counts['agence_waiting'] > 0)
                · {{ $counts['agence_waiting'] }}
            @endif
        </button>
        <button type="button" class="deliv-type {{ $typeTab === 'domicile' ? 'deliv-type--active' : '' }}" wire:click="$set('typeTab', 'domicile')">
            {{ __('Domicile') }}
            @if ($listScope === 'waiting' && $counts['domicile_waiting'] > 0)
                · {{ $counts['domicile_waiting'] }}
            @endif
        </button>
    </div>

    <section class="card app-table-card {{ $listScope === 'waiting' ? 'app-table-card--waiting' : '' }}">
        <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap;">
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('N° commande, client…') }}" style="flex:1;min-width:200px;">
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Commande') }}</th>
                        <th>{{ __('Type') }}</th>
                        @if ($listScope === 'waiting')
                            <th>{{ __('Paiement') }}</th>
                        @endif
                        <th>{{ __('Responsable') }}</th>
                        @if ($listScope === 'done')
                            <th>{{ __('Remise le') }}</th>
                        @else
                            <th>{{ __('Statut') }}</th>
                        @endif
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        @php
                            $order = $delivery->order;
                            $canHandOver = $order ? $settlement->canDeliver($order) : false;
                            $balance = $order ? (float) $order->balance : 0;
                        @endphp
                        <tr wire:key="del-{{ $delivery->id }}">
                            <td>
                                <strong>{{ $order?->number }}</strong>
                                <div class="deliv-meta">{{ $order?->client?->full_name }}</div>
                                @php
                                    $clientPhone = $order?->client?->whatsapp ?: $order?->client?->phone;
                                @endphp
                                @if ($clientPhone)
                                    <div class="deliv-meta" style="margin-top:2px;">
                                        <a href="tel:{{ preg_replace('/\s+/', '', $clientPhone) }}" style="color:#0f766e;text-decoration:none;font-weight:600;">
                                            {{ $clientPhone }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $delivery->type === 'agence' ? 'badge-info' : 'badge-neutral' }}">
                                    {{ __($types[$delivery->type] ?? $delivery->type) }}
                                </span>
                            </td>
                            @if ($listScope === 'waiting')
                                <td>
                                    @if ($canHandOver && $balance <= 0)
                                        <span class="pay-badge pay-badge--ok">{{ __('Soldé') }}</span>
                                    @elseif ($canHandOver)
                                        <span class="pay-badge pay-badge--credit">{{ __('Crédit validé') }}</span>
                                        <div class="deliv-meta" style="margin-top:2px;">{{ __('reste') }} {{ number_format($balance, 0, ',', ' ') }}</div>
                                    @elseif ($order?->credit_status === 'pending')
                                        <span class="pay-badge pay-badge--pending">{{ __('Crédit en attente') }}</span>
                                        <div class="deliv-meta" style="margin-top:2px;color:#c2410c;">{{ __('reste') }} {{ number_format($balance, 0, ',', ' ') }}</div>
                                    @else
                                        <span class="pay-badge pay-badge--due">{{ __('Impayé') }}</span>
                                        <div class="deliv-meta" style="margin-top:2px;color:#c2410c;">{{ __('reste') }} {{ number_format($balance, 0, ',', ' ') }}</div>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $delivery->driver?->name ?? ($delivery->type === 'agence' ? __('Réception') : '—') }}</td>
                            <td>
                                @if ($listScope === 'done')
                                    <span style="white-space:nowrap;">{{ $delivery->delivered_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                @else
                                    {{ __($statuses[$delivery->status] ?? $delivery->status) }}
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                @if ($listScope === 'waiting' && $canUpdate)
                                    @if ($delivery->type === 'domicile' && $delivery->status === 'pending')
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="markInTransit({{ $delivery->id }})">{{ __('En route') }}</button>
                                    @endif
                                    @if (in_array($delivery->status, ['pending', 'in_transit'], true))
                                        @if ($canHandOver)
                                            <button type="button" class="btn btn-primary btn-sm" wire:click="markDelivered({{ $delivery->id }})">
                                                {{ $delivery->type === 'agence' ? __('Remis au client') : __('Livré') }}
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="openCreditPanel({{ $delivery->id }})">
                                                {{ __('Régler / Crédit') }}
                                            </button>
                                        @endif
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $listScope === 'waiting' ? 6 : 5 }}" style="text-align:center;padding:32px;color:#64748b;">
                                @if ($listScope === 'done')
                                    {{ __('Aucune livraison effectuée pour l’instant.') }}
                                @else
                                    {{ __('Aucune livraison en attente.') }}
                                    @if ($canCreate)
                                        <div style="margin-top:10px;">
                                            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Planifier une livraison') }}</button>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;">{{ $deliveries->links() }}</div>
    </section>

    @if ($handoverFocus && $handoverFocus->order)
        @php $ho = $handoverFocus->order; @endphp
        <div class="deliv-modal-backdrop" wire:click.self="closeHandoverPanel" wire:key="handover-modal">
            <div class="deliv-modal" role="dialog" aria-modal="true" aria-labelledby="deliv-handover-title">
                <div class="deliv-modal__head">
                    <div>
                        <h3 id="deliv-handover-title" class="deliv-modal__title">{{ __('Remise —') }} {{ $ho->number }}</h3>
                        <p class="deliv-modal__hint">
                            <strong>{{ $ho->client?->full_name }}</strong>
                            @if ($ho->client?->whatsapp ?: $ho->client?->phone)
                                · {{ $ho->client?->whatsapp ?: $ho->client?->phone }}
                            @endif
                            <br>{{ __('Indiquez les consommables utilisés (cintres, emballage, étiquettes). Quantité 0 = non utilisé.') }}
                        </p>
                    </div>
                    <button type="button" class="deliv-modal__close" wire:click="closeHandoverPanel" aria-label="{{ __('Fermer') }}">×</button>
                </div>
                <div class="deliv-modal__body">
                    <div style="display:grid;gap:10px;">
                        @forelse ($handoverLines as $index => $line)
                            <div class="deliv-consumable" wire:key="ho-line-{{ $line['item_id'] }}">
                                <div style="flex:1;min-width:140px;">
                                    <strong style="font-size:13px;">{{ $line['name'] }}</strong>
                                    <div style="font-size:11px;color:#64748b;">
                                        {{ __('Stock') }} {{ number_format((float) $line['stock'], 0, ',', ' ') }} {{ $line['unit'] }}
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <label style="font-size:12px;color:#64748b;">{{ __('Qté') }}</label>
                                    <input class="input" style="width:90px;" type="number" min="0" step="1"
                                           wire:model="handoverLines.{{ $index }}.quantity">
                                    <span style="font-size:12px;color:#94a3b8;">{{ $line['unit'] }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-warning" style="margin:0;">
                                {{ __('Aucun consommable de remise. Configurez-les dans le module Stock (catégorie Consommables).') }}
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="deliv-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="closeHandoverPanel">{{ __('Annuler') }}</button>
                    <button type="button" class="btn btn-primary"
                            wire:click="confirmHandover"
                            wire:confirm="{{ __('Confirmer la remise et débiter le stock des quantités saisies ?') }}">
                        {{ __('Confirmer la remise') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($creditFocus && $creditFocus->order)
        @php $o = $creditFocus->order; @endphp
        <div class="deliv-modal-backdrop" wire:click.self="closeCreditPanel" wire:key="credit-modal">
            <div class="deliv-modal deliv-modal--wide" role="dialog" aria-modal="true" aria-labelledby="deliv-credit-title">
                <div class="deliv-modal__head">
                    <div>
                        <h3 id="deliv-credit-title" class="deliv-modal__title">{{ __('Règlement avant remise —') }} {{ $o->number }}</h3>
                        <p class="deliv-modal__hint">
                            <strong>{{ $o->client?->full_name }}</strong>
                            · {{ __('Solde') }}
                            <strong style="color:#c2410c;">{{ number_format((float) $o->balance, 0, ',', ' ') }} FCFA</strong>
                            · {{ __('Total') }} {{ number_format((float) $o->total, 0, ',', ' ') }}
                            · {{ __('Payé') }} {{ number_format((float) $o->amount_paid, 0, ',', ' ') }}
                        </p>
                    </div>
                    <button type="button" class="deliv-modal__close" wire:click="closeCreditPanel" aria-label="{{ __('Fermer') }}">×</button>
                </div>
                <div class="deliv-modal__body">
                    @if ($canPay)
                        <div style="padding:14px;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc;margin-bottom:14px;">
                            <div style="font-size:12px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px;">
                                {{ __('Encaisser le solde') }}
                            </div>

                            @if (count($existingPayments) > 0)
                                <div style="margin-bottom:12px;">
                                    <div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;">{{ __('Historique') }}</div>
                                    <div style="max-height:120px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;background:#fff;">
                                        @foreach ($existingPayments as $payment)
                                            <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 10px;border-bottom:1px solid #f1f5f9;font-size:12px;">
                                                <span>{{ $payment['paid_at'] }} · {{ $payment['method'] }}</span>
                                                <strong>{{ number_format((float) $payment['amount'], 0, ',', ' ') }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ((float) $o->balance > 0.009)
                                <div class="form-grid" style="margin-bottom:12px;">
                                    <div class="field">
                                        <label class="field-label">{{ __('Mode') }}</label>
                                        <select class="input" wire:model="payment_method">
                                            @foreach ($paymentMethods as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">{{ __('Montant') }}</label>
                                        <input class="input" type="number" step="0.01" min="0.01" wire:model="payment_amount">
                                        @error('payment_amount')
                                            <div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="field" style="grid-column:1/-1;">
                                        <label class="field-label">{{ __('Référence') }}</label>
                                        <input class="input" wire:model="payment_reference" placeholder="{{ __('Optionnel') }}">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary" wire:click="savePayment" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="savePayment">{{ __('Encaisser') }}</span>
                                    <span wire:loading wire:target="savePayment">{{ __('En cours…') }}</span>
                                </button>
                            @else
                                <div class="alert alert-success" style="margin:0 0 10px;">
                                    {{ __('Commande soldée. Vous pouvez remettre au client.') }}
                                </div>
                                @if ($canUpdate && in_array($creditFocus->status, ['pending', 'in_transit'], true))
                                    <button type="button" class="btn btn-primary"
                                            wire:click="markDelivered({{ $creditFocus->id }})">
                                        {{ $creditFocus->type === 'agence' ? __('Remis au client') : __('Livré') }}
                                    </button>
                                @endif
                            @endif
                        </div>
                    @endif

                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        @if ($canRequestCredit && ! in_array($o->credit_status, ['pending', 'approved'], true) && (float) $o->balance > 0)
                            <div style="flex:1;min-width:220px;">
                                <input class="input input-sm" wire:model="credit_notes" placeholder="{{ __('Motif du crédit (optionnel)') }}">
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm"
                                    wire:click="requestCredit({{ $creditFocus->id }})"
                                    wire:confirm="{{ __('Demander un crédit de :amount FCFA ?', ['amount' => number_format((float) $o->balance, 0, ',', ' ')]) }}">
                                {{ __('Demander un crédit') }}
                            </button>
                        @endif
                    </div>

                    @if ($o->credit_status === 'pending')
                        <div style="margin-top:14px;padding:12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;">
                            <strong>{{ __('Crédit en attente') }}</strong> —
                            {{ number_format((float) $o->credit_amount, 0, ',', ' ') }} FCFA
                            @if ($o->credit_notes)
                                <div style="font-size:12px;color:#64748b;margin-top:4px;">{{ $o->credit_notes }}</div>
                            @endif

                            @if ($canValidateCredit)
                                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center;">
                                    <button type="button" class="btn btn-primary btn-sm"
                                            wire:click="approveCredit({{ $creditFocus->id }})"
                                            wire:confirm="{{ __('Valider ce crédit ? La remise sera alors autorisée.') }}">
                                        {{ __('Valider le crédit') }}
                                    </button>
                                    <input class="input input-sm" style="max-width:220px;" wire:model="credit_reject_reason" placeholder="{{ __('Motif du refus') }}">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                            wire:click="rejectCredit({{ $creditFocus->id }})"
                                            wire:confirm="{{ __('Refuser ce crédit ?') }}">
                                        {{ __('Refuser') }}
                                    </button>
                                </div>
                            @else
                                <p style="margin:8px 0 0;font-size:12px;color:#b45309;">
                                    {{ __('En attente d’un profil habilité à valider les crédits.') }}
                                </p>
                            @endif
                        </div>
                    @elseif ($o->credit_status === 'approved')
                        <div class="alert alert-success" style="margin-top:12px;">
                            {{ __('Crédit validé. Vous pouvez marquer comme') }}
                            <button type="button" class="btn btn-primary btn-sm" style="margin-left:8px;"
                                    wire:click="markDelivered({{ $creditFocus->id }})">
                                {{ $creditFocus->type === 'agence' ? __('Remis au client') : __('Livré') }}
                            </button>
                        </div>
                    @elseif ($o->credit_status === 'rejected')
                        <div class="alert alert-error" style="margin-top:12px;">
                            {{ __('Crédit refusé') }}{{ $o->credit_rejection_reason ? ' : '.$o->credit_rejection_reason : '' }}. {{ __('Encaissez le solde ou refaites une demande.') }}
                        </div>
                    @endif
                </div>
                <div class="deliv-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="closeCreditPanel">{{ __('Fermer') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showForm)
        <div class="deliv-modal-backdrop" wire:click.self="cancel" wire:key="plan-modal">
            <div class="deliv-modal deliv-modal--wide" role="dialog" aria-modal="true" aria-labelledby="deliv-plan-title">
                <div class="deliv-modal__head">
                    <div>
                        <h3 id="deliv-plan-title" class="deliv-modal__title">{{ __('Planifier une livraison') }}</h3>
                        <p class="deliv-modal__hint">{{ __('Réservé aux commandes déjà emballées (statut Prêt).') }}</p>
                    </div>
                    <button type="button" class="deliv-modal__close" wire:click="cancel" aria-label="{{ __('Fermer') }}">×</button>
                </div>
                <div class="deliv-modal__body">
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label">{{ __('Commande') }}</label>
                            <select class="input" wire:model="order_id">
                                <option value="">—</option>
                                @foreach ($readyOrders as $order)
                                    <option value="{{ $order->id }}">{{ $order->number }} — {{ $order->client?->full_name }}</option>
                                @endforeach
                            </select>
                            @error('order_id')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label class="field-label">{{ __('Type') }}</label>
                            <select class="input" wire:model.live="type">
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($type === 'domicile')
                            <div class="field">
                                <label class="field-label">{{ __('Chauffeur') }}</label>
                                <select class="input" wire:model="driver_user_id">
                                    <option value="">—</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="field-label">{{ __('Véhicule') }}</label>
                                <input class="input" wire:model="vehicle">
                            </div>
                            <div class="field" style="grid-column:1/-1;">
                                <label class="field-label">{{ __('Adresse *') }}</label>
                                <input class="input" wire:model="address">
                                @error('address')<div class="text-error" style="font-size:12px;">{{ $message }}</div>@enderror
                            </div>
                        @endif
                        <div class="field" style="grid-column:1/-1;">
                            <label class="field-label">{{ __('Notes') }}</label>
                            <input class="input" wire:model="notes">
                        </div>
                    </div>
                </div>
                <div class="deliv-modal__foot">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">{{ __('Annuler') }}</button>
                    <button type="button" class="btn btn-primary" wire:click="save">{{ __('Enregistrer') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
