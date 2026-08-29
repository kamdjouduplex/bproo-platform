@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif

    @if ($invoice)
        @php
            $deliveryProgress = $invoiceDeliveryProgress ?? ['status' => 'n/a', 'ordered' => 0, 'delivered' => 0, 'remaining' => 0];
            $statusSummary = $invoiceStatusSummary ?? ['facts' => [], 'next' => null];
        @endphp
        <div class="invoice-workspace">
            <div class="invoice-workspace__head">
                <div>
                    <a class="invoice-workspace__back" href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}">← Factures</a>
                    <h2 class="invoice-workspace__title">
                        {{ $invoice->invoice_number }}
                        @if (($deliveryProgress['status'] ?? '') === 'partial')
                            <span class="badge badge-warning" style="vertical-align:middle;margin-left:8px;font-size:12px;">Livraison partielle</span>
                        @elseif (($deliveryProgress['status'] ?? '') === 'delivered')
                            <span class="badge badge-success" style="vertical-align:middle;margin-left:8px;font-size:12px;">Livré</span>
                        @endif
                    </h2>
                    <p class="invoice-workspace__client">
                        {{ $invoice->client?->name ?? '—' }}
                        · {{ \InovCom\Invoicing\Models\Invoice::declarationLabel($invoice->declaration_type) }}
                        @if ($invoice->quotation)
                            · Devis {{ $invoice->quotation->number }}
                        @endif
                    </p>
                </div>
                <div class="invoice-workspace__actions">
                    @if ($canCreateDelivery)
                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoicing.deliveries.create', ['invoice' => $invoice->id, 'tenant' => $tenantCode]) }}">
                            {{ ($deliveryProgress['status'] ?? '') === 'partial' ? 'Compléter la livraison' : 'Créer une livraison' }}
                        </a>
                    @endif
                    @if ($canPay)
                        <a class="btn {{ $canCreateDelivery ? 'btn-secondary' : 'btn-primary' }} btn-sm" href="{{ route('tenant.invoice_payments.pay', [$invoice->id, 'tenant' => $tenantCode]) }}">Encaisser facture</a>
                    @elseif (in_array($invoice->status, ['issued','partial','paid']) && ($hasPaymentHistory ?? false))
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$invoice->id, 'tenant' => $tenantCode]) }}">Encaissements</a>
                    @endif
                    @if (!in_array($invoice->status, ['draft', 'cancelled']))
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.print', [$invoice->id, 'tenant' => $tenantCode]) }}">Imprimer</a>
                    @endif
                    @if ($canUpdate && $invoice->isDraft())
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="deleteInvoice"
                                wire:confirm="Supprimer définitivement ce brouillon ?">Supprimer</button>
                    @endif
                    @if ($canCancel && ! $invoice->isDraft() && ! in_array($invoice->status, ['paid', 'cancelled']) && (float) $invoice->amount_paid <= 0)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelInvoice"
                                wire:confirm="Annuler cette facture ? Elle ne pourra plus être encaissée.">Annuler</button>
                    @endif
                </div>
            </div>

            @if (($statusSummary['facts'] ?? []) !== [])
                <dl class="invoice-status-facts">
                    @foreach ($statusSummary['facts'] as $fact)
                        <div class="invoice-status-facts__item invoice-status-facts__item--{{ $fact['tone'] }}">
                            <dt>{{ $fact['label'] }}</dt>
                            <dd>{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (!empty($statusSummary['next']))
                    <p class="invoice-status-facts__next">{{ $statusSummary['next'] }}</p>
                @endif
            @endif
        </div>

        @if ($invoice->status !== 'cancelled' && ($deliveryProgress['status'] ?? 'n/a') !== 'n/a')
            <div class="card" style="margin-bottom:16px; padding:14px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                    <div>
                        <strong>Livraison</strong>
                        <p style="margin:4px 0 0; font-size:12px; color:#6b7280;">
                            {{ fmt_num($deliveryProgress['delivered'] ?? 0) }}
                            / {{ fmt_num($deliveryProgress['ordered'] ?? 0) }} livré(s)
                            @if (($deliveryProgress['remaining'] ?? 0) > 0.0001)
                                — reste {{ fmt_num($deliveryProgress['remaining']) }}
                            @endif
                        </p>
                    </div>
                    <span class="badge {{ ($deliveryProgress['status'] ?? '') === 'delivered' ? 'badge-success' : (($deliveryProgress['status'] ?? '') === 'partial' ? 'badge-warning' : 'badge-secondary') }}">
                        {{ \InovCom\Invoicing\Services\DeliveryNotesService::deliveryStatusLabel($deliveryProgress['status'] ?? 'pending') }}
                    </span>
                </div>
                <div style="background:#e5e7eb; border-radius:4px; height:8px; margin-top:10px;">
                    @php
                        $orderedQty = max(0.0001, (float) ($deliveryProgress['ordered'] ?? 0));
                        $deliveredPct = min(100, max(0, ((float) ($deliveryProgress['delivered'] ?? 0) / $orderedQty) * 100));
                    @endphp
                    <div style="background:#16a34a; height:8px; border-radius:4px; width:{{ $deliveredPct }}%;"></div>
                </div>
                @if (($invoiceDeliveryNotes ?? collect())->isNotEmpty())
                    <div class="table-scroll" style="margin-top:12px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>N° BL</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoiceDeliveryNotes as $note)
                                    <tr>
                                        <td><strong>{{ $note->delivery_number }}</strong></td>
                                        <td>{{ $note->delivery_date?->format('d/m/Y') ?? '—' }}</td>
                                        <td>{{ \InovCom\Invoicing\Models\DeliveryNote::statusLabel($note->status) }}</td>
                                        <td>
                                            @if (\Illuminate\Support\Facades\Route::has('tenant.invoicing.deliveries.show'))
                                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.show', ['deliveryNote' => $note->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif (($deliveryProgress['status'] ?? '') === 'delivered')
                    <p style="font-size:13px;color:#166534;margin-top:10px;">Livraison complète. Aucun nouveau BL n’est nécessaire.</p>
                @endif
            </div>
        @endif

        @if (in_array($invoice->status, ['issued','partial','paid']))
            <div class="card" style="margin-bottom:16px; padding:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <strong>Encaissements</strong>
                    @if ($canPay)
                        <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoice_payments.pay', [$invoice->id, 'tenant' => $tenantCode]) }}">Encaisser facture</a>
                    @endif
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px;font-size:13px;">
                    <div><span style="color:#6b7280;">Total facture</span><br><strong>{{ fmt_money($invoice->total) }}</strong></div>
                    <div><span style="color:#6b7280;">Encaissé</span><br><strong style="color:#166534;">{{ fmt_money($invoice->amount_paid) }}</strong></div>
                    <div><span style="color:#6b7280;">Solde restant</span><br><strong style="color:{{ $invoice->balance > 0.01 ? '#b45309' : '#166534' }};">{{ fmt_money(max(0, $invoice->balance)) }}</strong></div>
                </div>
                @if ($invoice->hasClientCredit())
                    <p style="font-size:13px;color:#1d4ed8;margin-top:6px;"><strong>Crédit client à rembourser :</strong> {{ fmt_money($invoice->clientCreditAmount()) }} FCFA</p>
                @endif
                <div style="background:#e5e7eb; border-radius:4px; height:8px; margin-top:8px;">
                    <div style="background:#16a34a; height:8px; border-radius:4px; width:{{ $invoice->paymentProgressPercent() }}%;"></div>
                </div>
                @if (($invoicePayments ?? collect())->count() > 0)
                    <div class="table-scroll" style="margin-top:12px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>N° reçu</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Mode</th>
                                    <th>Par</th>
                                    <th>Solde après</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoicePayments as $pay)
                                    <tr style="{{ $pay->isCancelled() ? 'opacity:0.6;' : '' }}">
                                        <td>{{ $pay->reference }}</td>
                                        <td>{{ $pay->payment_date->format('d/m/Y') }} {{ $pay->created_at?->format('H:i') }}</td>
                                        <td style="{{ (float) $pay->amount < 0 ? 'color:#b91c1c;font-weight:600;' : ($pay->isActive() ? 'color:#166534;font-weight:600;' : '') }}">
                                            {{ (float) $pay->amount < 0 ? '−' : '+' }}{{ fmt_money(abs((float) $pay->amount)) }}
                                        </td>
                                        <td>{{ \InovCom\InvoicePayments\Models\InvoicePayment::methodLabel($pay->payment_method) }}</td>
                                        <td style="font-size:12px;">{{ $pay->creator?->name ?? '—' }}</td>
                                        <td>{{ $pay->balance_after !== null ? fmt_money(max(0, (float) $pay->balance_after)) : '—' }}</td>
                                        <td>
                                            @if (\Illuminate\Support\Facades\Route::has('tenant.invoice_payments.receipt.print'))
                                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoice_payments.receipt.print', ['invoicePayment' => $pay->id, 'tenant' => $tenantCode]) }}">Reçu</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif (!$canPay && $invoice->status === 'paid')
                    <p style="font-size:13px;color:#166534;margin-top:10px;">Facture totalement soldée.</p>
                @endif
            </div>
        @endif

        @if (in_array($invoice->status, ['issued', 'partial']) && (float) $invoice->balance > 0.01)
            <div class="card" style="margin-bottom:16px; padding:14px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                    <div>
                        <strong>Paiement échelonné</strong>
                        <p style="margin:4px 0 0; font-size:12px; color:#6b7280;">
                            Optionnel — découpe le solde en tranches mensuelles. Chaque mois, la tranche devient due pour les relances. Les factures sans échéancier gardent le comportement actuel.
                        </p>
                    </div>
                    @if ($scheduleAmountDueNow !== null && $scheduleAmountDueNow > 0.01)
                        <span class="badge badge-info">Dû maintenant : {{ fmt_money($scheduleAmountDueNow) }}</span>
                    @endif
                </div>

                @if (($invoiceSchedules ?? collect())->count() > 0)
                    <div class="table-scroll" style="margin-top:12px;">
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
                                    @php
                                        $remaining = max(0, (float) $sch->amount_due - (float) $sch->amount_paid);
                                        $isDue = $sch->status === 'overdue' || ($remaining > 0.01 && $sch->due_date->lte(\Carbon\Carbon::today()));
                                    @endphp
                                    <tr style="{{ $isDue && $sch->status !== 'paid' ? 'background:#fff7ed;' : '' }}">
                                        <td>{{ $sch->installment_number }}</td>
                                        <td>{{ $sch->due_date->format('d/m/Y') }}</td>
                                        <td>{{ fmt_money($sch->amount_due) }}</td>
                                        <td style="color:#166534;">{{ fmt_money($sch->amount_paid) }}</td>
                                        <td style="font-weight:600;">{{ fmt_money($remaining) }}</td>
                                        <td>{{ \InovCom\Invoicing\Models\InvoiceSchedule::statusLabel($sch->status) }}</td>
                                        <td>
                                            @if ($canPay && $sch->isDue())
                                                <a class="btn btn-primary btn-sm"
                                                   href="{{ route('tenant.invoice_payments.pay', ['invoice' => $invoice->id, 'tenant' => $tenantCode, 'schedule' => $sch->id]) }}">
                                                    Encaisser
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($canManageSchedule ?? false)
                        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <label style="display:flex; align-items:center; gap:6px; font-size:13px;">
                                <input type="checkbox" wire:model="schedule_replace"> Remplacer l’échéancier
                            </label>
                            <button type="button" class="btn btn-secondary btn-sm"
                                    wire:click="clearInstallmentSchedule"
                                    wire:confirm="Supprimer l’échéancier ? (possible seulement si aucune tranche n’a encore reçu de paiement)">
                                Supprimer l’échéancier
                            </button>
                        </div>
                    @endif
                @endif

                @if ($canManageSchedule ?? false)
                    <div class="form-grid" style="margin-top:14px;">
                        <div class="form-group">
                            <label class="field-label">Nombre de mois</label>
                            <input class="input" type="number" min="2" max="36" wire:model="schedule_months">
                            @error('schedule_months') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="field-label">1ʳᵉ échéance</label>
                            <input class="input" type="date" wire:model="schedule_first_due">
                            @error('schedule_first_due') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group" style="display:flex; align-items:flex-end;">
                            <button type="button" class="btn btn-primary"
                                    wire:click="generateInstallmentSchedule"
                                    wire:confirm="Créer un échéancier mensuel sur le solde restant ({{ fmt_money($invoice->balance) }}) ?">
                                {{ ($invoiceSchedules ?? collect())->count() > 0 ? 'Régénérer l’échéancier' : 'Échelonner le solde' }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    @if (!$invoice && $deliveryNoteId)
        <div class="alert alert-info" style="margin-bottom:16px;">
            <strong>Facture de la commande complète</strong>
            (devis {{ $sourceDeliveryHint['quotation_number'] ?? $quotation_reference }}) — pas seulement ce BL.
            @if (!empty($sourceDeliveryHint))
                <p style="margin:8px 0 0;font-size:13px;">
                    Commandé : <strong>{{ fmt_num($sourceDeliveryHint['ordered']) }}</strong>
                    · déjà livré : <strong>{{ fmt_num($sourceDeliveryHint['delivered']) }}</strong>
                    (dont {{ $sourceDeliveryHint['bl_number'] }} : {{ fmt_num($sourceDeliveryHint['bl_qty']) }})
                    @if (($sourceDeliveryHint['remaining'] ?? 0) > 0.0001)
                        · reliquat : <strong>{{ fmt_num($sourceDeliveryHint['remaining']) }}</strong>
                    @endif
                </p>
                <p style="margin:6px 0 0;font-size:13px;">
                    Une fois émise, la facture restera marquée <strong>livraison partielle</strong> tant que le reliquat n’est pas livré.
                </p>
            @endif
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:8px;" wire:click="clearDeliveryNoteSource">Saisie manuelle</button>
        </div>
    @endif

    @if (!$invoice && !$deliveryNoteId && ($availableDeliveryNotes ?? collect())->isNotEmpty())
        <section class="card" style="margin-bottom:16px;padding:16px;max-width:960px;">
            <h3 class="form-section-title" style="border:none;padding:0;margin-bottom:8px;">Créer depuis un bon de livraison</h3>
            <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">
                Choisissez un BL validé non encore facturé, ou créez la facture manuellement ci-dessous.
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <div class="field" style="flex:1;min-width:260px;">
                    <label class="field-label">Bon de livraison</label>
                    <select class="input" wire:model="pendingDeliveryNoteId">
                        <option value="">— Sélectionner —</option>
                        @foreach ($availableDeliveryNotes as $dn)
                            <option value="{{ $dn->id }}">
                                {{ $dn->delivery_number }}
                                — {{ $dn->client?->name ?? $dn->quotation?->client?->name ?? 'Client' }}
                                ({{ $dn->delivery_date->format('d/m/Y') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-primary" wire:click="loadDeliveryNote">Charger le BL</button>
            </div>
        </section>
    @endif

    <form wire:submit.prevent="save(false)">
        <section class="card" style="margin-bottom: 16px;">
            <h3 class="form-section-title">Client et type de facture</h3>

            <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 14px;">
                <label class="field-label">Client *</label>
                @if ($clientPicker)
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid #bfdbfe; border-radius:8px; background:#f0f9ff;">
                        <div>
                            <strong>{{ $clientPicker['name'] }}</strong>
                            <span class="badge badge-secondary" style="margin-left:6px;">{{ $clientPicker['code'] }}</span>
                            <span class="badge badge-info">{{ $clientPicker['type_label'] }}</span>
                            @if (!empty($clientPicker['phone']) || !empty($clientPicker['email']))
                                <p class="field-hint" style="margin-top:6px;">
                                    {{ $clientPicker['phone'] ?? '' }}
                                    @if (!empty($clientPicker['phone']) && !empty($clientPicker['email'])) · @endif
                                    {{ $clientPicker['email'] ?? '' }}
                                </p>
                            @endif
                        </div>
                        @if ($canEdit && !$quotation_id)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="clearClient">Changer</button>
                        @endif
                    </div>
                    @if ($quotation_id)
                        <p class="field-hint">Client verrouillé (facture issue du devis).</p>
                    @endif
                @elseif ($canEdit && !$quotation_id)
                    <input class="input" type="search" wire:model.live.debounce.200ms="clientSearch" placeholder="Nom, code, téléphone… (min. 2 caractères)" autocomplete="off">
                    <div wire:loading wire:target="clientSearch" class="field-hint">Recherche en cours…</div>
                    @if (strlen(trim($clientSearch)) >= 2 && count($clientResults) === 0)
                        <p class="field-hint" wire:loading.remove wire:target="clientSearch">Aucun client trouvé.</p>
                    @endif
                    @if (count($clientResults) > 0)
                        <div style="margin-top:8px; max-height:240px; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                            @foreach ($clientResults as $c)
                                <button type="button" wire:click="selectClient({{ $c['id'] }})" wire:key="inv-client-{{ $c['id'] }}"
                                        style="display:block; width:100%; text-align:left; padding:10px 12px; border:none; border-bottom:1px solid #eee; background:transparent; cursor:pointer;">
                                    <strong>{{ $c['name'] }}</strong> <span class="field-hint">({{ $c['code'] }})</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="field-hint">Sélectionnez un client.</p>
                @endif
                @error('client_id') <span class="text-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="field-label">Type de facture</label>
                    <select class="input" wire:model="declaration_type" @disabled(!$canEdit || $invoice)>
                        <option value="declared">Avec déclaration (FTH…)</option>
                        <option value="non_declared">Sans déclaration (FTN…)</option>
                    </select>
                    <p class="field-hint">Deux séquences de numérotation distinctes selon le régime fiscal.</p>
                </div>
                <div class="form-group">
                    <label class="field-label">Date de facturation *</label>
                    <input class="input @error('invoice_date') input--invalid @enderror" type="date" wire:model="invoice_date" @disabled(!$canEdit)>
                    @error('invoice_date') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="field-label">Date d'échéance</label>
                    <input class="input @error('due_date') input--invalid @enderror" type="date" wire:model.live="due_date" @disabled(!$canEdit)>
                    @error('due_date') <span class="field-error">{{ $message }}</span> @enderror
                    @php
                        $activeDueDays = is_numeric($due_days_custom) ? (int) $due_days_custom : -1;
                        $duePresets = [0, 15, 30, 45, 60, 90];
                        $isCustomActive = !in_array($activeDueDays, $duePresets, true);
                    @endphp
                    <div style="margin-top:10px; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <span class="field-hint" style="flex-basis:100%; margin:0;">Calcul rapide de l'échéance :</span>
                            <select class="input" wire:model.live="due_days_base" @disabled(!$canEdit) style="min-width:220px;">
                                <option value="today">À partir d'aujourd'hui</option>
                                <option value="next_month_start">Début du mois prochain</option>
                            </select>
                            @foreach ($duePresets as $preset)
                                <button type="button"
                                        class="btn btn-sm {{ $activeDueDays === $preset ? 'btn-primary' : 'btn-secondary' }}"
                                        wire:click.prevent="setDueDateFromDays({{ $preset }})"
                                        @disabled(!$canEdit)>{{ $preset }} jours</button>
                            @endforeach
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <input class="input" type="number" min="0" step="1"
                                   wire:model.live="due_days_custom" placeholder="Autre (jours)"
                                   style="width:170px;{{ $isCustomActive ? 'border-color:#7c3aed;box-shadow:0 0 0 2px rgba(124,58,237,.25);' : '' }}"
                                   @disabled(!$canEdit)>
                            <button type="button" class="btn {{ $isCustomActive ? 'btn-primary' : 'btn-secondary' }} btn-sm" wire:click.prevent="applyCustomDueDays" @disabled(!$canEdit)>Appliquer</button>
                        </div>

                        <div class="field-hint">
                            Échéance retenue : <strong>{{ $activeDueDays }} jour(s)</strong> —
                            Date calculée : <strong>{{ $due_date ? \Carbon\Carbon::parse($due_date)->format('d/m/Y') : '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom: 16px;">
            <h3 class="form-section-title">Références sur le document imprimé</h3>
            @if ($requiresDocumentReferences ?? false)
                <p class="field-hint" style="margin:0 0 14px; padding:10px 12px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1e40af;">
                    Création directe : renseignez les références <strong>commande</strong>, <strong>devis</strong> et <strong>bon de livraison</strong> affichées sur la facture imprimée.
                </p>
            @endif
            <div class="form-grid">
                <div class="form-group @error('customer_reference') form-group--invalid @enderror">
                    <label class="field-label">N° commande / demande achat{{ ($requiresDocumentReferences ?? false) ? ' *' : '' }}</label>
                    <input class="input @error('customer_reference') input--invalid @enderror" wire:model="customer_reference" placeholder="Ex. DA-2026-014" @disabled(!$canEdit)>
                    @error('customer_reference') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group @error('quotation_reference') form-group--invalid @enderror">
                    <label class="field-label">N° devis{{ ($requiresDocumentReferences ?? false) ? ' *' : '' }}</label>
                    <input class="input @error('quotation_reference') input--invalid @enderror" wire:model="quotation_reference" placeholder="Ex. DEV-2026-000123" @disabled(!$canEdit)>
                    @error('quotation_reference') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group @error('delivery_note_number') form-group--invalid @enderror">
                    <label class="field-label">N° bon de livraison{{ ($requiresDocumentReferences ?? false) ? ' *' : '' }}</label>
                    <input class="input @error('delivery_note_number') input--invalid @enderror" wire:model="delivery_note_number" placeholder="Ex. BL-2026-000045" @disabled(!$canEdit || ($invoice && !$invoice->isEditable() && $deliveryNoteId))>
                    @if ($invoice && !$invoice->isEditable())
                        <p class="field-hint">Renseigné automatiquement lors de la validation d'un bon de livraison.</p>
                    @endif
                    @error('delivery_note_number') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="field-label">Mode de paiement (impression)</label>
                    <select class="input" wire:model="payment_mode" @disabled(!$canEdit)>
                        @foreach ($paymentModes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top: 4px;">
                <label class="field-label">Note de la facture</label>
                <textarea class="input" wire:model="notes" rows="4" placeholder="Commentaires visibles sur la facture…" @disabled(!$canEdit)></textarea>
            </div>
            <div class="form-group" style="margin-top: 4px;">
                <label class="field-label">Informations complémentaires (optionnel)</label>
                <textarea class="input" wire:model="additional_info" rows="2" placeholder="Ajouter d'autres informations à imprimer…" @disabled(!$canEdit)></textarea>
            </div>
        </section>

        @if ($canEdit && !$quotation_id)
        <section class="card" style="margin-bottom: 16px;">
            <h3 class="form-section-title">Ajouter des articles</h3>
            <input class="input" type="search" wire:model.live.debounce.200ms="itemSearch" placeholder="Désignation, référence ou code-barres… (min. 2 caractères)" autocomplete="off">
            <div wire:loading wire:target="itemSearch" class="field-hint">Recherche en cours…</div>
            @if (strlen(trim($itemSearch)) >= 2 && count($searchResults) === 0)
                <p class="field-hint" wire:loading.remove wire:target="itemSearch">Aucun article trouvé.</p>
            @endif
            @if (count($searchResults) > 0)
                <div style="margin-top:8px; max-height:260px; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px; background:#fff;">
                    @foreach ($searchResults as $item)
                        <button type="button" wire:click="addItemToCart({{ $item['id'] }})" wire:key="inv-item-{{ $item['id'] }}"
                                style="display:block; width:100%; text-align:left; padding:10px 12px; border:none; border-bottom:1px solid #eee; background:transparent; cursor:pointer;">
                            <div style="display:flex; justify-content:space-between;">
                                <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                <span>{{ fmt_money((float) $item['price']) }} FCFA</span>
                            </div>
                            @if(!empty($item['barcode']))
                                <span class="field-hint">{{ $item['barcode'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
        @endif

        <section class="card app-table-card" style="margin-bottom: 16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                <h3 class="form-section-title" style="border:none; padding:0; margin:0;">Lignes de facture</h3>
                @if ($quotation_id)
                    <p class="field-hint" style="margin:0;">Quantités de la commande (devis) — elles ne suivent pas le reliquat du BL.</p>
                @endif
                <div class="lines-discount-mode">
                    <span class="lines-discount-mode__label">Remise ligne</span>
                    <div class="lines-discount-mode__toggle" role="group" aria-label="Unité de remise par ligne">
                        <button
                            type="button"
                            class="lines-discount-mode__btn {{ $lines_discount_mode === 'percent' ? 'is-active' : '' }}"
                            wire:click="setLinesDiscountMode('percent')"
                            @disabled(!$canEdit)
                        >%</button>
                        <button
                            type="button"
                            class="lines-discount-mode__btn {{ $lines_discount_mode === 'amount' ? 'is-active' : '' }}"
                            wire:click="setLinesDiscountMode('amount')"
                            @disabled(!$canEdit)
                        >FCFA</button>
                    </div>
                </div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th style="width:56px;">N°</th>
                            <th>Référence / Article</th>
                            <th>Qté</th>
                            <th>P.U.</th>
                            <th>Remise unit.</th>
                            <th title="Saisissez le prix de vente souhaité — la remise se calcule">P.U. net</th>
                            <th>Total</th>
                            @if ($canEdit && !$quotation_id)<th></th>@endif
                        </tr>
                    </thead>
                    @php $lineColspan = 7 + ($canEdit && !$quotation_id ? 1 : 0); @endphp
                    <tbody>
                        @forelse ($cart as $index => $row)
                        @php
                            $pu = (float) ($row['unit_price'] ?? 0);
                            $lineMode = ($lines_discount_mode ?? 'percent') === 'amount' ? 'amount' : 'percent';
                            $lineInput = max(0, (float) ($row['line_discount'] ?? 0));
                            if ($lineMode === 'percent') {
                                $rem = min($pu, round($pu * min(100, $lineInput) / 100, 2));
                            } else {
                                $rem = min($pu, $lineInput);
                            }
                            $puNet = max(0, $pu - $rem);
                        @endphp
                        <tr>
                            <td style="text-align:center; font-weight:600;">{{ (int) ($row['line_number'] ?? (($index + 1) * 10)) }}</td>
                            <td>
                                <x-item-label :reference="$row['item_sku'] ?? null" :name="$row['item_name'] ?? null" />
                            </td>
                            <td><input class="input input-sm" type="number" step="0.001" wire:model.live="cart.{{ $index }}.quantity" @disabled(!$canEdit || $quotation_id) style="width:90px;"></td>
                            <td><input class="input input-sm" type="number" step="0.01" wire:model.live="cart.{{ $index }}.unit_price" @disabled(!$canEdit) style="width:110px;" placeholder=""></td>
                            <td>
                                <div class="quote-line-discount">
                                    <input
                                        class="input input-sm quote-line-discount__value"
                                        type="number"
                                        step="{{ $lineMode === 'percent' ? 'any' : '0.01' }}"
                                        min="0"
                                        @if ($lineMode === 'percent') max="100" @endif
                                        wire:model.live="cart.{{ $index }}.line_discount"
                                        @disabled(!$canEdit)
                                        placeholder="0"
                                    >
                                    @if ($lineInput > 0)
                                        <span class="quote-line-discount__unit">{{ $lineMode === 'percent' ? '%' : 'FCFA' }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <input
                                    class="input input-sm"
                                    type="number"
                                    step="1"
                                    min="0"
                                    @if ($pu > 0) max="{{ $pu }}" @endif
                                    wire:model.live="cart.{{ $index }}.unit_price_net"
                                    @disabled(!$canEdit)
                                    style="width:110px;"
                                    title="Prix de vente souhaité — la remise se calcule automatiquement"
                                >
                            </td>
                            <td>{{ fmt_money((float)($row['line_total'] ?? 0)) }}</td>
                            @if ($canEdit && !$quotation_id)
                            <td><button type="button" class="btn btn-secondary btn-sm" wire:click="removeFromCart({{ $index }})" title="Retirer">×</button></td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="{{ $lineColspan }}">Aucune ligne — recherchez et ajoutez des articles ci-dessus.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 20px;">
                <div style="max-width: 420px; margin-left: auto; width: 100%;">
                    <div @class([
                        'form-group',
                        'form-group--invalid' => $errors->has('discount_percent') || $errors->has('discount_amount'),
                    ])>
                        <label class="field-label">Remise globale</label>
                        <div class="quote-discount-row">
                            <select class="input quote-discount-row__mode" wire:model.live="discount_mode" @disabled(!$canEdit)>
                                <option value="percent">Pourcentage (%)</option>
                                <option value="amount">Montant fixe (FCFA)</option>
                            </select>
                            @if ($discount_mode === 'percent')
                                <div class="quote-discount-row__value">
                                    <input
                                        class="input quote-discount-row__input @error('discount_percent') input--invalid @enderror"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        wire:model.live="discount_percent"
                                        placeholder="0"
                                        @disabled(!$canEdit)
                                    >
                                    <span class="quote-discount-row__suffix">%</span>
                                </div>
                            @else
                                <div class="quote-discount-row__value">
                                    <input
                                        class="input quote-discount-row__input @error('discount_amount') input--invalid @enderror"
                                        type="number"
                                        step="1"
                                        min="0"
                                        wire:model.live="discount_amount"
                                        placeholder="0"
                                        @disabled(!$canEdit)
                                    >
                                    <span class="quote-discount-row__suffix">FCFA</span>
                                </div>
                            @endif
                        </div>
                        @error('discount_percent') <span class="field-error">{{ $message }}</span> @enderror
                        @error('discount_amount') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="document-tax-block">
                    <label class="field-label">Taxes</label>
                    <p class="document-tax-block__hint">
                        TVA <strong>+ Addition</strong> : s'ajoute au HT pour le TTC.
                        IS / IR <strong>− Soustraction</strong> : retenue sur le HT (net à payer = HT − IS).
                    </p>

                    <div class="document-tax-lines">
                        @foreach ($tax_lines as $i => $t)
                            @php
                                $isSubtract = ($t['effect'] ?? 'add') === 'subtract';
                                $taxAmountPreview = (float) ($t['amount'] ?? 0);
                            @endphp
                            <div wire:key="invoice-tax-line-{{ $i }}" @class(['document-tax-line', 'document-tax-line--subtract' => $isSubtract])>
                                <div class="document-tax-line__field document-tax-line__field--name">
                                    <span class="document-tax-line__label">Nom de la taxe</span>
                                    <input class="input" type="text" wire:model.live="tax_lines.{{ $i }}.name"
                                           placeholder="TVA, IS, IR…"
                                           @disabled(!$canEdit)>
                                </div>
                                <div class="document-tax-line__field">
                                    <span class="document-tax-line__label">Calcul</span>
                                    <select class="input" wire:model.live="tax_lines.{{ $i }}.mode" @disabled(!$canEdit)>
                                        <option value="percent">Pourcentage (%)</option>
                                        <option value="amount">Montant fixe</option>
                                    </select>
                                </div>
                                <div class="document-tax-line__field">
                                    <span class="document-tax-line__label">Effet</span>
                                    <select class="input" wire:model.live="tax_lines.{{ $i }}.effect" @disabled(!$canEdit)>
                                        <option value="add">+ Addition (TTC)</option>
                                        <option value="subtract">− Retenue (HT)</option>
                                    </select>
                                </div>
                                <div class="document-tax-line__field">
                                    <span class="document-tax-line__label">Valeur</span>
                                    @if (($t['mode'] ?? 'amount') === 'percent')
                                        <input class="input" type="number" min="0" step="0.001" wire:model.live="tax_lines.{{ $i }}.rate"
                                               placeholder="Taux %"
                                               @disabled(!$canEdit)>
                                    @else
                                        <input class="input" type="number" step="0.01" min="0" wire:model.live="tax_lines.{{ $i }}.amount"
                                               placeholder="Montant FCFA"
                                               @disabled(!$canEdit)>
                                    @endif
                                </div>
                                <div class="document-tax-line__actions">
                                    @if (($t['mode'] ?? 'amount') === 'percent' && $taxAmountPreview > 0)
                                        <span class="document-tax-line__amount-preview">= {{ fmt_money($taxAmountPreview) }}</span>
                                    @endif
                                    @if ($canEdit && count($tax_lines) > 1)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click.prevent="removeTaxLine({{ $i }})"
                                                title="Retirer cette taxe">×</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($canEdit)
                        <div style="margin-top:12px;">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click.prevent="addTaxLine">+ Ajouter une taxe</button>
                        </div>
                    @endif
                </div>

                <div class="document-totals-split">
                    @include('partials.document-margin-summary', [
                        'totalCost' => $marginSummary['total_cost'] ?? 0,
                        'margin' => $marginSummary['margin'] ?? 0,
                        'marginPercent' => $marginSummary['margin_percent'] ?? null,
                    ])
                    <div class="document-totals-split__financial" style="padding:14px 0 4px; font-size:14px;">
                @if ($discount > 0)
                    <div style="color:#b45309;">
                        Remise
                        @if (($discountPct ?? 0) > 0)
                            ({{ fmt_num($discountPct) }} %)
                        @elseif (($discountMode ?? 'percent') === 'amount')
                            (montant fixe)
                        @endif
                        : −{{ fmt_money($discount) }} FCFA
                    </div>
                @endif
                <div>Montant HT : <strong>{{ fmt_money($totalHt) }} FCFA</strong></div>
                @if ($tax != 0)
                    @foreach(($taxLinesComputed ?? []) as $line)
                        @if (($line['tax_amount'] ?? 0) > 0)
                            @php
                                $taxSubtract = ($line['tax_effect'] ?? 'add') === 'subtract';
                            @endphp
                            <div>
                                Montant {{ $line['tax_name'] ?? 'Taxe' }}
                                @if (($line['tax_mode'] ?? 'amount') === 'percent' && isset($line['tax_rate']))
                                    ({{ fmt_num((float) $line['tax_rate'], 2) }} %)
                                @endif
                                :
                                <strong>{{ $taxSubtract ? '−' : '+' }}{{ fmt_money((float) ($line['tax_amount'] ?? 0)) }} FCFA</strong>
                            </div>
                        @endif
                    @endforeach
                @endif
                @if (isset($ttc))
                    <div>Montant TTC : {{ fmt_money($ttc) }} FCFA</div>
                @endif
                <div style="font-size:1.15em; font-weight:700; margin-top:8px; padding-top:8px; border-top:2px solid #e5e7eb;">
                    Net à payer : {{ fmt_money($total) }} FCFA
                </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="page-actions" style="flex-wrap:wrap; gap:8px;">
            @if ($canEdit)
                <button type="submit" class="btn btn-primary">Enregistrer brouillon</button>
                @if ($canIssue)
                    <button type="button" class="btn btn-primary" wire:click="save(true)">Enregistrer et émettre</button>
                @endif
            @endif
            <a class="btn btn-secondary" href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}">Retour</a>
        </div>
    </form>
</div>



