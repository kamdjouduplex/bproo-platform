@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>
    @endif

    @if (\InovCom\Debts\Models\Debt::supportsValidationWorkflow() && !$debt->is_validated)
        <div class="alert alert-error" style="margin-bottom: 16px;">Aucun paiement autorisé: cette dette doit être validée.</div>
    @endif

    <section class="card" style="margin-bottom: 24px;">
        <h2 class="card-title">Dette {{ $debt->reference }}</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <div style="font-size: 12px; color: #6b7280;">Client</div>
                <strong>{{ $debt->client->name }} ({{ $debt->client->code }})</strong>
            </div>
            <div>
                <div style="font-size: 12px; color: #6b7280;">Montant total</div>
                <strong>{{ fmt_money($debt->total_amount) }} {{ currency_label() }}</strong>
            </div>
            <div>
                <div style="font-size: 12px; color: #6b7280;">Solde restant</div>
                <strong style="color: #dc2626;">{{ fmt_money($debt->balance) }} {{ currency_label() }}</strong>
            </div>
            <div>
                <div style="font-size: 12px; color: #6b7280;">Échéance</div>
                {{ $debt->due_date ? $debt->due_date->format('d/m/Y') : '-' }}
            </div>
        </div>
    </section>

    <form wire:submit.prevent="save">
        <section class="card">
            <h2 class="card-title">Enregistrer un paiement</h2>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Montant *</label>
                    <input class="input" wire:model="amount" type="number" step="0.01" min="0.01" required placeholder="{{ fmt_money($debt->balance) }}">
                </div>
                <div class="field">
                    <label class="field-label">Date du paiement *</label>
                    <input class="input" wire:model="payment_date" type="date" required>
                </div>
                <div class="field">
                    <label class="field-label">Méthode de paiement *</label>
                    <select class="input" wire:model="payment_method" required>
                        <option value="cash">Espèces</option>
                        <option value="check">Chèque</option>
                        <option value="bank_transfer">Virement</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Référence externe</label>
                    <input class="input" wire:model="external_reference" placeholder="N° chèque, transaction...">
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Notes</label>
                    <textarea class="input" wire:model="notes" rows="2" placeholder="Notes..."></textarea>
                </div>
            </div>
        </section>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.debts.edit', [$debt->id, 'tenant' => $tenantCode]) }}">Retour</a>
            <button type="submit" class="btn btn-primary" @disabled(\InovCom\Debts\Models\Debt::supportsValidationWorkflow() && !$debt->is_validated)>Enregistrer le paiement</button>
        </div>
    </form>

    @if ($debt->payments->count() > 0)
        <section class="card" style="margin-top: 24px;">
            <h2 class="card-title">Historique des paiements</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Enregistré par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($debt->payments as $pay)
                            <tr>
                                <td>{{ $pay->reference }}</td>
                                <td>{{ $pay->payment_date->format('d/m/Y') }}</td>
                                <td>{{ fmt_money($pay->amount) }} {{ currency_label() }}</td>
                                <td>
                                    @if ($pay->payment_method === 'cash') Espèces
                                    @elseif ($pay->payment_method === 'check') Chèque
                                    @elseif ($pay->payment_method === 'bank_transfer') Virement
                                    @elseif ($pay->payment_method === 'mobile_money') Mobile Money
                                    @else Autre
                                    @endif
                                </td>
                                <td>{{ $pay->creator->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
