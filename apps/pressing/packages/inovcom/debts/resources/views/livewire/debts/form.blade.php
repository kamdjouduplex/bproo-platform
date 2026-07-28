@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif

    @if ($debtId && $currentDebt)
        <section class="card" style="margin-bottom: 16px;">
            <h2 class="card-title">Détails utiles de la dette</h2>
            <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px;">
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Référence</div>
                    <strong>{{ $currentDebt->reference }}</strong>
                </div>
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Montant total</div>
                    <strong>{{ fmt_money((float) $currentDebt->total_amount) }} FCFA</strong>
                </div>
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Total remboursé</div>
                    <strong style="color:#166534;">{{ fmt_money((float) $totalPaid) }} FCFA</strong>
                </div>
                <div style="padding:12px; border:1px solid #e5e7eb; border-radius:8px;">
                    <div style="font-size:12px; color:#6b7280;">Solde restant</div>
                    <strong style="color:#b91c1c;">{{ fmt_money((float) $currentDebt->balance) }} FCFA</strong>
                </div>
            </div>

            <div style="margin-top:14px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">Client</div>
                    <strong>{{ $currentDebt->client->name ?? '-' }}</strong>
                    <div style="font-size:12px; color:#6b7280;">{{ $currentDebt->client->code ?? '' }}</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">Validation</div>
                    @if (\InovCom\Debts\Models\Debt::supportsValidationWorkflow())
                        @if ($currentDebt->is_validated)
                            <span class="badge badge-success">Validée</span>
                            <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                Par {{ $currentDebt->validator->name ?? 'N/A' }} le {{ optional($currentDebt->validated_at)->format('d/m/Y H:i') ?? '-' }}
                            </div>
                        @else
                            <span class="badge badge-error">En attente de validation</span>
                        @endif
                    @else
                        <span class="badge badge-warning">Validation non disponible (migration)</span>
                    @endif
                </div>
            </div>

            <div style="margin-top:14px;">
                <div style="display:flex; justify-content:space-between; font-size:12px; color:#6b7280;">
                    <span>Progression remboursement</span>
                    <span>{{ fmt_num($repaymentRate, 1) }}%</span>
                </div>
                <div style="margin-top:6px; height:10px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
                    <div style="height:100%; width:{{ $repaymentRate }}%; background:#2563eb;"></div>
                </div>
            </div>

            @if ($currentDebt->description)
                <div style="margin-top:12px; font-size:13px; color:#374151;">
                    <strong>Description:</strong> {{ $currentDebt->description }}
                </div>
            @endif
        </section>

        <section class="card" style="margin-bottom: 16px;">
            <h2 class="card-title">Historique des remboursements</h2>
            @if ($currentDebt->payments->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Référence</th>
                                <th>Méthode</th>
                                <th>Montant</th>
                                <th>Réf. externe</th>
                                <th>Saisi par</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($currentDebt->payments as $payment)
                                <tr>
                                    <td>{{ $payment->payment_date?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $payment->reference }}</td>
                                    <td>{{ $payment->payment_method }}</td>
                                    <td>{{ fmt_money((float) $payment->amount) }} FCFA</td>
                                    <td>{{ $payment->external_reference ?: '-' }}</td>
                                    <td>{{ $payment->creator->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin: 0;">Aucun remboursement enregistré pour cette dette.</div>
            @endif
        </section>
    @endif

    <form wire:submit.prevent="save">
        <section class="card">
            <h2 class="card-title">{{ $debtId ? 'Modifier la dette' : 'Nouvelle dette' }}</h2>

            <div class="field" style="margin-bottom: 16px;">
                <label class="field-label">Client *</label>
                <input class="input" wire:model.live.debounce.150ms="clientSearch" placeholder="Nom, code, email ou téléphone..." autocomplete="off" {{ $debtId ? 'readonly' : '' }}>
                @if (!$debtId && !empty($clientSearch) && !empty($clientResults))
                    <div style="margin-top: 8px; max-height: 260px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        @foreach ($clientResults as $c)
                            <div style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;" wire:click="selectClient({{ $c['id'] }})" wire:key="client-{{ $c['id'] }}">
                                <strong>{{ $c['name'] }}</strong> ({{ $c['code'] }})
                                <div style="font-size: 12px; color: #666;">Solde: {{ fmt_money((float)$c['current_balance']) }} FCFA</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Montant total *</label>
                    <input class="input" wire:model="total_amount" type="number" step="0.01" min="0.01" required {{ $debtId ? 'readonly' : '' }}>
                </div>
                <div class="field">
                    <label class="field-label">Date d'ouverture *</label>
                    <input class="input" wire:model="opened_at" type="date" required>
                </div>
                <div class="field">
                    <label class="field-label">Échéance</label>
                    <input class="input" wire:model="due_date" type="date">
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Description</label>
                    <textarea class="input" wire:model="description" rows="3" placeholder="Origine de la dette..."></textarea>
                </div>
            </div>
        </section>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.debts.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary">{{ $debtId ? 'Mettre à jour' : 'Créer la dette' }}</button>
            @if ($debtId)
                @if ($canReceivePayment && $currentDebt && (!\InovCom\Debts\Models\Debt::supportsValidationWorkflow() || $currentDebt->is_validated))
                    <a class="btn btn-primary" href="{{ route('tenant.debts.pay', [$debtId, 'tenant' => $tenantCode]) }}">Encaisser un paiement</a>
                @else
                    <span class="badge badge-error">
                        @if (!$canReceivePayment)
                            Permission d'encaissement requise
                        @else
                            Validation requise avant encaissement
                        @endif
                    </span>
                @endif
            @endif
        </div>
    </form>
</div>
