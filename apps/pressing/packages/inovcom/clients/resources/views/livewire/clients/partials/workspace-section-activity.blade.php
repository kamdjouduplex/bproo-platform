@php
        $statusBadge = function (?string $status) {
            $map = [
                'paid' => 'badge-success', 'accepted' => 'badge-success', 'confirmed' => 'badge-success',
                'partial' => 'badge-warning', 'sent' => 'badge-warning', 'issued' => 'badge-warning', 'open' => 'badge-warning',
                'overdue' => 'badge-error', 'rejected' => 'badge-error', 'cancelled' => 'badge-error',
                'draft' => 'badge-info', 'suspended' => 'badge-info', 'superseded' => 'badge-info',
            ];
            return $map[$status] ?? 'badge-info';
        };

        $statusLabel = function (?string $status) {
            $map = [
                // Factures
                'draft' => 'Brouillon', 'issued' => 'Émise', 'partial' => 'Partiellement payée',
                'paid' => 'Payée', 'cancelled' => 'Annulée', 'superseded' => 'Remplacée',
                // Devis
                'sent' => 'Envoyé', 'accepted' => 'Accepté', 'suspended' => 'Suspendu', 'rejected' => 'Rejeté',
                // Dettes
                'open' => 'Ouverte', 'overdue' => 'En retard', 'partially_paid' => 'Partiellement payée',
                'confirmed' => 'Confirmée',
            ];
            return $map[$status] ?? ($status ? ucfirst($status) : '—');
        };

        // Sépare les factures impayées (solde > 0) des factures soldées.
        $invoicesUnpaid = $history['invoices']->filter(fn ($i) => (float) $i->balance > 0.01)->values();
        $invoicesPaid = $history['invoices']->filter(fn ($i) => (float) $i->balance <= 0.01)->values();

        $historyTabs = [
            'quotations' => 'Devis (' . $history['quotations']->count() . ')',
            'invoices_unpaid' => 'Factures impayées (' . $invoicesUnpaid->count() . ')',
            'invoices_paid' => 'Factures payées (' . $invoicesPaid->count() . ')',
            'reminders' => 'Relances (' . $reminders->count() . ')',
            'avoirs' => 'Avoirs (' . $history['creditNotes']->count() . ')',
            'sales' => 'Ventes (' . $history['sales']->count() . ')',
            'debts' => 'Dettes (' . $history['debts']->count() . ')',
            'product_ref' => 'Par référence produit',
        ];
    @endphp

    <div class="client-360-subtabs">
        @foreach ($historyTabs as $key => $label)
            <button type="button"
                class="client-360-subtabs__btn {{ $activeHistoryTab === $key ? 'client-360-subtabs__btn--active' : '' }}"
                wire:click="setHistoryTab('{{ $key }}')">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="client-360-subpanel">

        {{-- VENTES --}}
        <div @if($activeHistoryTab !== 'sales') style="display:none;" @endif>
            @if ($history['sales']->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>N° Vente</th><th>Date</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($history['sales'] as $sale)
                                <tr>
                                    <td>{{ $sale->sale_number ?? ('#' . $sale->id) }}</td>
                                    <td>{{ \Carbon\Carbon::parse($sale->sale_date ?? $sale->created_at)->format('d/m/Y') }}</td>
                                    <td>{{ fmt_money((float) $sale->total) }} FCFA</td>
                                    <td>
                                        @if (Route::has('tenant.sales.show'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.sales.show', [$sale->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucune vente enregistrée.</div>
            @endif
        </div>

        {{-- DEVIS --}}
        <div @if($activeHistoryTab !== 'quotations') style="display:none;" @endif>
            @if ($history['quotations']->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>N° Devis</th><th>Date</th><th>Validité</th><th>Statut</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($history['quotations'] as $q)
                                <tr>
                                    <td>{{ $q->number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($q->quote_date)->format('d/m/Y') }}</td>
                                    <td>{{ $q->valid_until ? \Carbon\Carbon::parse($q->valid_until)->format('d/m/Y') : '—' }}</td>
                                    <td><span class="badge {{ $statusBadge($q->status) }}">{{ $statusLabel($q->status) }}</span></td>
                                    <td>{{ fmt_money((float) $q->total) }} FCFA</td>
                                    <td>
                                        @if (Route::has('tenant.quotations.edit'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.quotations.edit', [$q->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucun devis enregistré.</div>
            @endif
        </div>

        {{-- FACTURES IMPAYÉES --}}
        <div @if($activeHistoryTab !== 'invoices_unpaid') style="display:none;" @endif>
            @if ($invoicesUnpaid->count() > 0)
                @php
                    $unpaidTotal = $invoicesUnpaid->sum(fn ($i) => (float) $i->total);
                    $unpaidBalance = $invoicesUnpaid->sum(fn ($i) => (float) $i->balance);
                @endphp
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>N° Facture</th><th>Date</th><th>Échéance</th><th>Statut</th><th>Total</th><th>Solde</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($invoicesUnpaid as $inv)
                                @php $overdue = $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->isPast(); @endphp
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') }}</td>
                                    <td>
                                        {{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '—' }}
                                        @if ($overdue) <span class="badge badge-error badge-sm">En retard</span> @endif
                                    </td>
                                    <td><span class="badge {{ $statusBadge($inv->status) }}">{{ $statusLabel($inv->status) }}</span></td>
                                    <td>{{ fmt_money((float) $inv->total) }} FCFA</td>
                                    <td><strong>{{ fmt_money((float) $inv->balance) }} FCFA</strong></td>
                                    <td>
                                        @if (Route::has('tenant.invoicing.edit'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$inv->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr style="background:#fffbeb; font-weight:600;">
                                <td colspan="4">Total impayé</td>
                                <td>{{ fmt_money($unpaidTotal) }} FCFA</td>
                                <td colspan="2"><strong>{{ fmt_money($unpaidBalance) }} FCFA</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucune facture impayée.</div>
            @endif
        </div>

        {{-- FACTURES PAYÉES --}}
        <div @if($activeHistoryTab !== 'invoices_paid') style="display:none;" @endif>
            @if ($invoicesPaid->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>N° Facture</th><th>Date</th><th>Échéance</th><th>Statut</th><th>Total</th><th>Solde</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($invoicesPaid as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') }}</td>
                                    <td>{{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '—' }}</td>
                                    <td><span class="badge {{ $statusBadge($inv->status) }}">{{ $statusLabel($inv->status) }}</span></td>
                                    <td>{{ fmt_money((float) $inv->total) }} FCFA</td>
                                    <td>{{ fmt_money((float) $inv->balance) }} FCFA</td>
                                    <td>
                                        @if (Route::has('tenant.invoicing.edit'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.edit', [$inv->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucune facture payée.</div>
            @endif
        </div>

        {{-- DETTES --}}
        <div @if($activeHistoryTab !== 'debts') style="display:none;" @endif>
            @if (! $debtsModule)
                <div class="alert" style="margin:0;">Module Dettes non disponible.</div>
            @elseif ($history['debts']->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Référence</th><th>Date</th><th>Échéance</th><th>Montant</th><th>Solde</th><th>Statut</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($history['debts'] as $debt)
                                <tr>
                                    <td>{{ $debt->reference }}</td>
                                    <td>{{ \Carbon\Carbon::parse($debt->opened_at)->format('d/m/Y') }}</td>
                                    <td>{{ $debt->due_date ? \Carbon\Carbon::parse($debt->due_date)->format('d/m/Y') : '—' }}</td>
                                    <td>{{ fmt_money((float) $debt->total_amount) }} FCFA</td>
                                    <td>{{ fmt_money((float) $debt->balance) }} FCFA</td>
                                    <td><span class="badge {{ $statusBadge($debt->status) }}">{{ $statusLabel($debt->status) }}</span></td>
                                    <td>
                                        @if (Route::has('tenant.debts.edit'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.debts.edit', [$debt->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucune dette enregistrée.</div>
            @endif
        </div>

        {{-- AVOIRS --}}
        <div @if($activeHistoryTab !== 'avoirs') style="display:none;" @endif>
            @php
                $creditNoteStatus = [
                    'draft' => ['Brouillon', 'badge-secondary'],
                    'validated' => ['Validé', 'badge-info'],
                    'partially_used' => ['Partiellement utilisé', 'badge-warning'],
                    'used' => ['Utilisé', 'badge-success'],
                    'cancelled' => ['Annulé', 'badge-error'],
                ];
            @endphp
            @if ($history['creditNotes']->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>N° Avoir</th><th>Date</th><th>Total</th><th>Reste</th><th>Statut</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($history['creditNotes'] as $cn)
                                @php $cs = $creditNoteStatus[$cn->status] ?? [ucfirst((string) $cn->status), 'badge-secondary']; @endphp
                                <tr>
                                    <td>{{ $cn->credit_note_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($cn->issue_date)->format('d/m/Y') }}</td>
                                    <td>{{ fmt_money((float) $cn->total) }} FCFA</td>
                                    <td><strong>{{ fmt_money((float) $cn->remaining_amount) }} FCFA</strong></td>
                                    <td><span class="badge {{ $cs[1] }}">{{ $cs[0] }}</span></td>
                                    <td>
                                        @if (Route::has('tenant.returns.credit_notes.show'))
                                            <a class="btn btn-secondary btn-sm" href="{{ route('tenant.returns.credit_notes.show', [$cn->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucun avoir enregistré.</div>
            @endif
        </div>

        {{-- RELANCES (lecture seule — l'enregistrement se fait dans le module Facturation) --}}
        <div @if($activeHistoryTab !== 'reminders') style="display:none;" @endif>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
                <span style="font-size:12px; color:#6b7280;">Historique des relances effectuées auprès du client.</span>
                @if (Route::has('tenant.invoicing.collection_reminders.index'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.collection_reminders.index', ['tenant' => $tenantCode]) }}">Enregistrer une relance (Facturation)</a>
                @endif
            </div>

            @if ($reminders->count() > 0)
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Date</th><th>Niveau</th><th>Canal</th><th>Montant</th><th>Note</th></tr></thead>
                        <tbody>
                            @foreach ($reminders as $reminder)
                                <tr>
                                    <td>{{ optional($reminder->sent_at)->format('d/m/Y H:i') ?? optional($reminder->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $reminder->levelLabel() }}</td>
                                    <td>{{ $reminder->channelLabel() }}</td>
                                    <td>{{ fmt_money((float) $reminder->amount_due) }} FCFA</td>
                                    <td>{{ $reminder->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert" style="margin:0;">Aucune relance enregistrée.</div>
            @endif
        </div>

        {{-- HISTORIQUE PAR RÉFÉRENCE PRODUIT --}}
        <div @if($activeHistoryTab !== 'product_ref') style="display:none;" @endif>
            <p style="font-size:12px; color:#6b7280; margin:0 0 12px;">
                Recherchez toutes les lignes contenant une référence produit (SKU) pour ce client : devis, factures, bons de livraison et ventes caisse.
            </p>

            <div class="form-grid" style="margin-bottom:16px;">
                <div class="field">
                    <label class="field-label">Référence produit (SKU)</label>
                    <input class="input" wire:model="productRefSearch" placeholder="Ex: REF-001">
                    @error('productRefSearch') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Date début</label>
                    <input class="input" type="date" wire:model="productDateFrom">
                    @error('productDateFrom') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">Date fin</label>
                    <input class="input" type="date" wire:model="productDateTo">
                    @error('productDateTo') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field" style="display:flex; align-items:flex-end; gap:8px;">
                    <button type="button" class="btn btn-primary" wire:click="searchProductHistory">Rechercher</button>
                    @if (count($productHistoryResults) > 0)
                        <button type="button" class="btn btn-secondary" wire:click="exportProductHistory">Exporter Excel</button>
                    @endif
                </div>
            </div>

            @if (count($productHistoryResults) > 0)
                @php
                    $productQtyTotal = collect($productHistoryResults)->sum(fn ($r) => (float) ($r['quantity'] ?? 0));
                    $productAmountTotal = collect($productHistoryResults)->sum(fn ($r) => (float) ($r['line_total'] ?? 0));
                @endphp
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>N° document</th>
                                <th>Date</th>
                                <th>Réf.</th>
                                <th>Désignation</th>
                                <th style="text-align:right;">Qté</th>
                                <th style="text-align:right;">P.U.</th>
                                <th style="text-align:right;">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productHistoryResults as $row)
                                <tr>
                                    <td><span class="badge badge-info">{{ $row['type_label'] ?? $row['type'] }}</span></td>
                                    <td>{{ $row['document_number'] }}</td>
                                    <td>{{ \Carbon\Carbon::parse($row['document_date'])->format('d/m/Y') }}</td>
                                    <td>{{ $row['item_sku'] ?? '—' }}</td>
                                    <td>{{ $row['item_name'] }}</td>
                                    <td style="text-align:right;">{{ fmt_num((float) $row['quantity']) }}</td>
                                    <td style="text-align:right;">{{ fmt_money((float) $row['unit_price']) }}</td>
                                    <td style="text-align:right;">{{ fmt_money((float) $row['line_total']) }} FCFA</td>
                                    <td>
                                        @if (! empty($row['link']))
                                            <a class="btn btn-secondary btn-sm" href="{{ $row['link'] }}">Voir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr style="background:#f8fafc; font-weight:600;">
                                <td colspan="5">Total ({{ count($productHistoryResults) }} ligne(s))</td>
                                <td style="text-align:right;">{{ fmt_num($productQtyTotal) }}</td>
                                <td></td>
                                <td style="text-align:right;">{{ fmt_money($productAmountTotal) }} FCFA</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @elseif (trim($productRefSearch) !== '')
                <div class="alert" style="margin:0;">Aucune ligne trouvée pour la référence « {{ $productRefSearch }} ».</div>
            @else
                <div class="alert" style="margin:0;">Saisissez une référence produit et lancez la recherche.</div>
            @endif
        </div>
    </div>
