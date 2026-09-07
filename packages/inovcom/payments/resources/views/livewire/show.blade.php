<div class="page-body">
    @php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
    <style>
        @@media (max-width: 800px) {
            .payment-show-grid { grid-template-columns: 1fr !important; }
        }
    </style>
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.index', ['tenant' => $tenantCode]) }}">&larr; Encaissements</a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if (\Illuminate\Support\Facades\Route::has('tenant.invoice_payments.receipt.print'))
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.receipt.print', ['invoicePayment' => $payment->id, 'tenant' => $tenantCode]) }}">Reçu</a>
            @endif
            @if ($invoice && \Illuminate\Support\Facades\Route::has('tenant.invoicing.edit'))
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$invoice->id, 'tenant' => $tenantCode]) }}">Facture</a>
            @endif
            @if ($canReceive)
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$invoice->id, 'tenant' => $tenantCode]) }}">Encaisser</a>
            @endif
        </div>
    </div>

    <div class="payment-show-grid" style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,1fr);gap:16px;align-items:start;">
        <section class="card" style="padding:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                <div>
                    <div class="table-title" style="margin-bottom:4px;">{{ $payment->reference }}</div>
                    <p style="margin:0;font-size:13px;color:#6b7280;">
                        {{ $payment->payment_date->format('d/m/Y') }}
                        @if ($payment->created_at)
                            · {{ $payment->created_at->format('H:i') }}
                        @endif
                        · {{ \InovCom\InvoicePayments\Models\InvoicePayment::methodLabel($payment->payment_method) }}
                        · {{ $payment->creator?->name ?? '—' }}
                    </p>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    @if ($payment->isCancelled())
                        <span class="badge badge-error">Annulé</span>
                    @else
                        <span class="badge {{ ($settlement['status'] ?? '') === 'paid' ? 'badge-success' : 'badge-warning' }}">
                            {{ $settlement['status_label'] }}
                        </span>
                    @endif
                </div>
            </div>

            @include('inovcom-invoice-payments::partials.settlement-lines', ['settlement' => $settlement])
            @if (! $payment->isCancelled())
                <p style="margin:10px 0 0;font-size:12px;color:#6b7280;">
                    Statut de la facture après cet encaissement :
                    <strong>{{ $settlement['status_label'] }}</strong>
                </p>
            @endif

            @if ($payment->notes)
                <p style="margin:12px 0 0;font-size:13px;color:#4b5563;"><strong>Notes :</strong> {{ $payment->notes }}</p>
            @endif
            @if ($payment->external_reference)
                <p style="margin:8px 0 0;font-size:13px;color:#4b5563;"><strong>Réf. transaction :</strong> {{ $payment->external_reference }}</p>
            @endif
        </section>

        <div>
            <section class="card" style="padding:16px;margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Facture liée</div>
                @if ($invoice)
                    <div style="display:grid;gap:10px;font-size:13px;">
                        <div><span style="color:#6b7280;">N° facture</span><br><strong>{{ $invoice->invoice_number }}</strong></div>
                        <div><span style="color:#6b7280;">Client</span><br>{{ $invoice->client?->name ?? '—' }}</div>
                        @if ($invoice->quotation?->number || $invoice->quotation_reference)
                            <div><span style="color:#6b7280;">N° devis</span><br>{{ $invoice->quotation?->number ?: $invoice->quotation_reference }}</div>
                        @endif
                        @if ($invoice->customer_reference)
                            <div><span style="color:#6b7280;">Bon de commande</span><br>{{ $invoice->customer_reference }}</div>
                        @endif
                        @if ($invoice->delivery_note_number)
                            <div><span style="color:#6b7280;">Bon de livraison</span><br>{{ $invoice->delivery_note_number }}</div>
                        @endif
                        <div><span style="color:#6b7280;">Total facture</span><br><strong>{{ fmt_money($invoice->total) }}</strong></div>
                        <div>
                            <span style="color:#6b7280;">Statut facture</span><br>
                            {{ \InovCom\Invoicing\Models\Invoice::statusLabel($invoice->status) }}
                        </div>
                    </div>
                @else
                    <p style="color:#6b7280;margin:0;">Facture introuvable.</p>
                @endif
            </section>

            @if ($payment->hasSourceWithholding())
            <section class="card" style="padding:16px;margin-bottom:16px;">
                <div class="table-title" style="margin-bottom:12px;">Justificatif / attestation</div>
                @forelse (($attachments ?? collect()) as $att)
                    @include('inovcom-invoice-payments::partials.attachment-row', [
                        'att' => $att,
                        'paymentId' => $payment->id,
                        'tenantCode' => $tenantCode,
                        'canReplace' => $canAttach ?? false,
                    ])
                @empty
                    <p style="margin:0 0 12px;font-size:13px;color:#6b7280;">Aucun justificatif pour le moment.</p>
                @endforelse

                @if ($canAttach ?? false)
                    <div style="margin-top:{{ ($attachments ?? collect())->isNotEmpty() ? '12px' : '0' }};">
                        <input class="input input-sm" type="file" wire:key="new-cert-{{ $attachments->count() }}" wire:model="newCertificate" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <div wire:loading wire:target="newCertificate" style="font-size:12px;color:#6b7280;margin-top:4px;">Chargement…</div>
                        @error('newCertificate') <div class="text-error">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-primary btn-sm" style="margin-top:8px;" wire:click="attachCertificate">Joindre</button>
                    </div>
                @endif
            </section>
            @endif

            @if ($payment->isCancelled())
                <section class="card" style="padding:16px;border-color:#fecaca;">
                    <div class="table-title" style="margin-bottom:8px;color:#b91c1c;">Annulation</div>
                    <p style="margin:0;font-size:13px;">{{ $payment->cancellation_reason ?: '—' }}</p>
                    <p style="margin:8px 0 0;font-size:12px;color:#6b7280;">
                        {{ $payment->cancelled_at?->format('d/m/Y à H:i') }} · {{ $payment->canceller?->name ?? '—' }}
                    </p>
                </section>
            @endif
        </div>
    </div>
</div>
