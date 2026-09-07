<div class="page-body">
    @php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif

    <div class="card" style="margin-bottom: 16px; padding: 16px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="margin:0;">Facture {{ $invoice->invoice_number }}</h3>
                <p style="margin:6px 0 0;color:#6b7280;">Client : <strong>{{ $invoice->client->name }}</strong></p>
            </div>
            <span class="badge badge-info">{{ \InovCom\Invoicing\Models\Invoice::statusLabel($invoice->status) }}</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:16px;">
            <div style="padding:12px;background:#f9fafb;border-radius:6px;">
                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Montant HT</div>
                <div style="font-size:18px;font-weight:700;">{{ fmt_money($fiscal->ht) }}</div>
            </div>
            @if ($fiscal->vat > 0)
                <div style="padding:12px;background:#eff6ff;border-radius:6px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">TVA</div>
                    <div style="font-size:18px;font-weight:700;color:#1d4ed8;">{{ fmt_money($fiscal->vat) }}</div>
                </div>
            @endif
            @if ($fiscal->is > 0)
                <div style="padding:12px;background:#eff6ff;border-radius:6px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">IS</div>
                    <div style="font-size:18px;font-weight:700;color:#1d4ed8;">{{ fmt_money($fiscal->is) }}</div>
                    @if ($fiscal->isSubtractive)
                        <div style="font-size:11px;color:#6b7280;">Déduit à l’émission</div>
                    @endif
                </div>
            @endif
            <div style="padding:12px;background:#f9fafb;border-radius:6px;">
                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">TTC / net à payer</div>
                <div style="font-size:18px;font-weight:700;">{{ fmt_money($invoice->total) }}</div>
            </div>
            <div style="padding:12px;background:#f0fdf4;border-radius:6px;">
                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Déjà soldé</div>
                <div style="font-size:18px;font-weight:700;color:#166534;">{{ fmt_money($invoice->amount_paid) }}</div>
            </div>
            <div style="padding:12px;background:{{ $invoice->balance > 0.01 ? '#fffbeb' : '#f0fdf4' }};border-radius:6px;">
                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Solde restant</div>
                <div style="font-size:18px;font-weight:700;color:{{ $invoice->balance > 0.01 ? '#b45309' : '#166534' }};">
                    {{ fmt_money(max(0, $invoice->balance)) }} FCFA
                </div>
            </div>
        </div>

        <div style="background:#e5e7eb; border-radius:4px; height:10px; margin-top:12px;">
            <div style="background:#16a34a; height:10px; border-radius:4px; width:{{ $invoice->paymentProgressPercent() }}%;"></div>
        </div>
    </div>

    @if (($invoiceSchedules ?? collect())->count() > 0)
        <div class="card" style="margin-bottom:16px; padding:16px;">
            <h3 style="margin:0 0 8px;">Échéancier</h3>
            <p style="margin:0 0 12px; font-size:12px; color:#6b7280;">
                @if (($scheduleAmountDueNow ?? 0) > 0.01)
                    Dû maintenant : <strong>{{ fmt_money($scheduleAmountDueNow) }} FCFA</strong>
                @endif
            </p>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Échéance</th>
                            <th>Montant</th>
                            <th>Payé</th>
                            <th>Reste</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoiceSchedules as $sch)
                            @php $remaining = max(0, (float) $sch->amount_due - (float) $sch->amount_paid); @endphp
                            <tr>
                                <td>{{ $sch->installment_number }}</td>
                                <td>{{ $sch->due_date->format('d/m/Y') }}</td>
                                <td>{{ fmt_money($sch->amount_due) }}</td>
                                <td>{{ fmt_money($sch->amount_paid) }}</td>
                                <td><strong>{{ fmt_money($remaining) }}</strong></td>
                                <td>{{ \InovCom\Invoicing\Models\InvoiceSchedule::statusLabel($sch->status) }}</td>
                                <td>
                                    @if ($canReceive && $sch->isDue())
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="paySchedule({{ $sch->id }})">Encaisser</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($canReceive)
        <form wire:submit.prevent="save" class="card" style="padding: 16px; margin-bottom: 16px;">
            <h3 style="margin-bottom: 12px;">Nouvel encaissement</h3>
            @if ($targetSchedule ?? null)
                <div class="alert" style="margin-bottom:12px;background:#eff6ff;border:1px solid #93c5fd;padding:12px;">
                    Échéance n°{{ $targetSchedule->installment_number }}
                    ({{ $targetSchedule->due_date->format('d/m/Y') }})
                    — montant prérempli : <strong>{{ fmt_money($targetSchedule->remaining()) }}</strong>.
                </div>
            @endif
            <div class="form-grid">
                <div class="form-group">
                    <label class="field-label">Montant à percevoir (FCFA) *</label>
                    <input class="input" type="number" step="1" min="0" max="{{ (int) round((float) $invoice->balance) }}"
                           wire:model.live="amount">
                    @error('amount') <span class="text-error">{{ $message }}</span> @enderror
                    <button type="button" class="btn btn-secondary btn-sm" style="margin-top:6px;" wire:click="payFullBalance">
                        Recalculer le montant à percevoir
                    </button>
                </div>
                <div class="form-group">
                    <label class="field-label">Date d'encaissement *</label>
                    <input class="input" type="date" wire:model="payment_date">
                    @error('payment_date') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="field-label">Mode de paiement *</label>
                    <select class="input" wire:model="payment_method">
                        <option value="cash">Espèces</option>
                        <option value="check">Chèque</option>
                        <option value="bank_transfer">Virement bancaire</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="field-label">Réf. transaction</label>
                    <input class="input" wire:model="external_reference" placeholder="N° chèque, virement…">
                </div>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="field-label">Notes</label>
                <textarea class="input" wire:model="notes" rows="2"></textarea>
            </div>

            <div style="margin-top:16px;padding:14px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                    <strong>Retenues</strong>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        @foreach ($withholdingTypes as $type)
                            @php $kind = $type->resolvedKind(); @endphp
                            @if ($kind === 'vat' && ($remainingVat ?? 0) <= 0)
                                @continue
                            @endif
                            @if ($kind === 'is' && ($fiscal->isSubtractive || (($fiscal->locksIsAmount() ?? false) && ($remainingIs ?? 0) <= 0)))
                                @continue
                            @endif
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="addWithholding({{ $type->id }})">
                                + {{ $type->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @if ($canManageWithholdings ?? false)
                    <p style="font-size:12px;margin-bottom:8px;">
                        <a href="{{ route('tenant.invoice_payments.withholding_types', ['tenant' => $tenantCode]) }}">Configurer les types de retenues</a>
                    </p>
                @endif
                @if (count($withholdings) > 0)
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Base</th>
                                    <th>Taux %</th>
                                    <th>Montant retenu</th>
                                    <th>Compte</th>
                                    <th>N° / note</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($withholdings as $index => $row)
                                    @php $kind = $row['kind'] ?? 'other'; @endphp
                                    <tr wire:key="wh-{{ $index }}">
                                        <td>
                                            <select class="input input-sm" wire:model.live="withholdings.{{ $index }}.type_id">
                                                <option value="">Choisir…</option>
                                                @foreach ($withholdingTypes as $type)
                                                    @php $optionKind = $type->resolvedKind(); @endphp
                                                    @if ($optionKind === 'vat' && ($remainingVat ?? 0) <= 0 && $kind !== 'vat')
                                                        @continue
                                                    @endif
                                                    @if ($optionKind === 'is' && ($fiscal->isSubtractive || (($fiscal->locksIsAmount() ?? false) && ($remainingIs ?? 0) <= 0)) && $kind !== 'is')
                                                        @continue
                                                    @endif
                                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                            <div style="font-size:11px;color:#6b7280;margin-top:4px;">
                                                @if ($kind === 'vat') TVA de la facture
                                                @elseif ($kind === 'is' && ($fiscal->locksIsAmount() ?? false)) IS de la facture
                                                @elseif ($kind === 'is') IS — base × taux
                                                @else Base × taux
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <input class="input input-sm" type="number" step="1" min="0"
                                                   wire:model.live="withholdings.{{ $index }}.base_amount"
                                                   @if ($kind === 'vat' || ($kind === 'is' && ($fiscal->locksIsAmount() ?? false))) readonly @endif
                                                   placeholder="{{ $kind === 'is' ? 'Base / bénéfice' : 'Base' }}"
                                                   style="width:120px;">
                                        </td>
                                        <td>
                                            <input class="input input-sm" type="number" step="0.01" min="0"
                                                   wire:model.live="withholdings.{{ $index }}.rate"
                                                   @if ($kind === 'vat' || ($kind === 'is' && ($fiscal->locksIsAmount() ?? false))) readonly @endif
                                                   style="width:80px;">
                                        </td>
                                        <td>
                                            <input class="input input-sm" type="number" step="1" min="0"
                                                   wire:model.live="withholdings.{{ $index }}.amount"
                                                   @if ($kind === 'vat' || ($kind === 'is' && ($fiscal->locksIsAmount() ?? false))) readonly @endif
                                                   style="width:110px;">
                                        </td>
                                        <td><input class="input input-sm" wire:model="withholdings.{{ $index }}.account_code" placeholder="Compte" style="width:90px;"></td>
                                        <td><input class="input input-sm" wire:model="withholdings.{{ $index }}.comment" placeholder="N° attestation…"></td>
                                        <td><button type="button" class="btn btn-secondary btn-sm" wire:click="removeWithholding({{ $index }})">×</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p style="font-size:12px;color:#6b7280;margin:0;">Aucune retenue.</p>
                @endif
            </div>

            @php $s = $settlement ?? null; @endphp
            @if ($s)
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:14px;">
                    <div style="padding:10px;background:#f9fafb;border-radius:6px;">
                        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Total facture</div>
                        <strong>{{ fmt_money($s['invoice_total']) }}</strong>
                    </div>
                    <div style="padding:10px;background:#f0fdf4;border-radius:6px;">
                        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Net à percevoir</div>
                        <strong style="color:#166534;">{{ fmt_money($s['cash_received']) }}</strong>
                    </div>
                    <div style="padding:10px;background:#eff6ff;border-radius:6px;">
                        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Total des retenues</div>
                        <strong style="color:#1d4ed8;">{{ fmt_money($s['withholding_total']) }}</strong>
                    </div>
                    <div style="padding:10px;background:#111;color:#fff;border-radius:6px;">
                        <div style="font-size:11px;text-transform:uppercase;opacity:.8;">Total réglé</div>
                        <strong>{{ fmt_money($s['settled']) }}</strong>
                    </div>
                    <div style="padding:10px;background:{{ $s['exceeds'] ? '#fef2f2' : '#fffbeb' }};border-radius:6px;">
                        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Solde restant</div>
                        <strong style="color:{{ $s['exceeds'] ? '#b91c1c' : '#b45309' }};">{{ fmt_money(max(0, $s['remaining'])) }}</strong>
                    </div>
                </div>
                @if ($s['exceeds'])
                    <p style="margin-top:10px;color:#b91c1c;font-size:13px;">
                        Incohérence : montant encaissé + retenues dépasse le solde de la facture.
                    </p>
                @endif
            @endif
            @if (count($withholdings) > 0)
            <div class="form-group" style="margin-top:14px;">
                <label class="field-label">Attestation / justificatif de retenue</label>
                <input class="input" type="file" wire:model="newCertificate" accept=".pdf,.jpg,.jpeg,.png,.webp">
                @error('newCertificate') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            @endif
            <div class="page-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Enregistrer et imprimer le reçu</button>
                <a class="btn btn-secondary" href="{{ route('tenant.invoicing.edit', ['invoice' => $invoice->id, 'tenant' => $tenantCode]) }}">Retour facture</a>
            </div>
        </form>
    @else
        <div class="alert" style="margin-bottom:16px;background:#f0fdf4;border:1px solid #86efac;padding:14px;">
            <strong>Facture soldée</strong> — aucun nouvel encaissement possible. Vous pouvez consulter l'historique et réimprimer les reçus ci-dessous.
        </div>
        <div class="page-actions" style="margin-bottom:16px;">
            <a class="btn btn-secondary" href="{{ route('tenant.invoicing.edit', ['invoice' => $invoice->id, 'tenant' => $tenantCode]) }}">Retour facture</a>
        </div>
    @endif

    <section class="card app-table-card">
        <div class="table-title" style="padding:12px 16px;">
            <strong>Historique des encaissements</strong>
            <span style="font-weight:normal;color:#6b7280;margin-left:8px;">{{ $payments->count() }} opération(s)</span>
        </div>
        @if ($payments->count() > 0)
            @php $historyNeedsCertificate = $payments->contains(fn ($p) => $p->hasSourceWithholding()); @endphp
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N° reçu</th>
                            <th>Date / heure</th>
                            <th>Perçu</th>
                            <th>Retenues</th>
                            <th>Mode</th>
                            <th>Par</th>
                            <th>Solde après</th>
                            @if ($historyNeedsCertificate)
                                <th>Justificatif</th>
                            @endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $p)
                            <tr wire:key="pay-{{ $p->id }}" style="{{ $p->isCancelled() ? 'opacity:0.65;' : '' }}">
                                <td>
                                    <strong>{{ $p->reference }}</strong>
                                    <div style="font-size:11px;color:#9ca3af;">{{ \InovCom\InvoicePayments\Models\InvoicePayment::statusLabel($p->status) }}</div>
                                </td>
                                <td>
                                    {{ $p->payment_date->format('d/m/Y') }}
                                    <div style="font-size:11px;color:#9ca3af;">{{ $p->created_at?->format('H:i') }}</div>
                                </td>
                                <td style="{{ (float) $p->amount < 0 ? 'color:#b91c1c;' : ($p->isActive() ? 'color:#166534;font-weight:600;' : '') }}">
                                    {{ (float) $p->amount < 0 ? '−' : '+' }}{{ fmt_money(abs((float) $p->amount)) }}
                                    @if ($p->external_reference)
                                        <div style="font-size:11px;color:#6b7280;">{{ $p->external_reference }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->withholdingTotal() > 0)
                                        <strong style="color:#1d4ed8;">{{ fmt_money($p->withholdingTotal()) }}</strong>
                                        @foreach ($p->withholdings as $wh)
                                            <div style="font-size:11px;color:#1e40af;">
                                                {{ $wh->type_name }} : {{ fmt_money($wh->amount) }}
                                                @if ((float) $wh->base_amount > 0)
                                                    <span style="color:#6b7280;">(base {{ fmt_money($wh->base_amount) }}@if((float)$wh->rate > 0) × {{ fmt_num((float)$wh->rate, 2) }} %@endif)</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <span style="color:#9ca3af;">—</span>
                                    @endif
                                </td>
                                <td>{{ \InovCom\InvoicePayments\Models\InvoicePayment::methodLabel($p->payment_method) }}</td>
                                <td>{{ $p->creator?->name ?? '—' }}</td>
                                <td>
                                    @if ($p->balance_after !== null)
                                        {{ fmt_money(max(0, (float) $p->balance_after)) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($historyNeedsCertificate)
                                <td style="min-width:180px;">
                                    @if ($p->hasSourceWithholding())
                                        @foreach ($p->attachments as $att)
                                            @include('inovcom-invoice-payments::partials.attachment-row', [
                                                'att' => $att,
                                                'paymentId' => $p->id,
                                                'tenantCode' => $tenantCode,
                                                'canReplace' => $p->isActive(),
                                            ])
                                        @endforeach
                                        @if ($p->isActive())
                                            <input type="file" class="input input-sm" wire:model="historyCertificates.{{ $p->id }}" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:4px;"
                                                    wire:click="attachHistoryCertificate({{ $p->id }})">Joindre</button>
                                            @error('historyCertificates.'.$p->id) <div class="text-error">{{ $message }}</div> @enderror
                                        @endif
                                    @else
                                        <span style="color:#9ca3af;">—</span>
                                    @endif
                                </td>
                                @endif
                                <td style="white-space:nowrap;">
                                    @if (\Illuminate\Support\Facades\Route::has('tenant.invoice_payments.show'))
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.show', ['invoicePayment' => $p->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                    @endif
                                    @if ($p->isReceipt() || $p->isActive())
                                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.receipt.print', ['invoicePayment' => $p->id, 'tenant' => $tenantCode]) }}">Reçu</a>
                                    @endif
                                    @if ($p->isReceipt() && $canCancel && $cancellingPaymentId !== $p->id)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="startCancel({{ $p->id }})">Annuler</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($cancellingPaymentId === $p->id)
                                <tr>
                                    <td colspan="{{ $historyNeedsCertificate ? 9 : 8 }}" style="background:#fffbeb;padding:12px;">
                                        <label class="field-label">Motif d'annulation *</label>
                                        <textarea class="input" wire:model="cancellation_reason" rows="2" style="margin-bottom:8px;"></textarea>
                                        @error('cancellation_reason') <span class="text-error">{{ $message }}</span> @enderror
                                        <div style="display:flex;gap:8px;">
                                            <button type="button" class="btn btn-primary btn-sm" wire:click="confirmCancel"
                                                    wire:confirm="Confirmer l'annulation de cet encaissement ?">Confirmer l'annulation</button>
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelCancel">Fermer</button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="padding:16px;color:#6b7280;">Aucun encaissement enregistré.</p>
        @endif
    </section>
</div>
