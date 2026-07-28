@php
    $tenantCode = $tenantCode ?? request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $printAllUrl = route('tenant.invoicing.collection_reminders.print', $filterQueryParams);
    $pdfUrl = route('tenant.invoicing.collection_reminders.pdf', $filterQueryParams);
@endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom: 16px;">{{ session('error') }}</div>@endif

    <section class="card app-table-card" style="margin-bottom: 16px;">
        <div class="table-toolbar">
            <div class="table-title">Fiches de relance</div>
            <form wire:submit.prevent="applyFilters" class="collection-reminders-toolbar" role="search">
                <input
                    class="input input-sm"
                    type="text"
                    wire:model="clientSearch"
                    placeholder="Client (nom ou code)"
                    style="min-width: 160px;"
                    aria-label="Rechercher un client"
                >
                <select class="input input-sm" wire:model="clientFilter" aria-label="Client">
                    <option value="">Tous les clients</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <input class="input input-sm" type="date" wire:model="invoiceDateFrom" title="Facture du" aria-label="Facture du">
                <input class="input input-sm" type="date" wire:model="invoiceDateTo" title="Facture au" aria-label="Facture au">
                <input class="input input-sm" type="date" wire:model="dueDateFrom" title="Échéance du" aria-label="Échéance du">
                <input class="input input-sm" type="date" wire:model="dueDateTo" title="Échéance au" aria-label="Échéance au">
                <input
                    class="input input-sm"
                    type="number"
                    min="0"
                    wire:model="minDaysOverdue"
                    placeholder="Retard min. (j)"
                    style="width: 110px;"
                    title="Nombre minimum de jours de retard"
                    aria-label="Retard minimum en jours"
                >
                <select class="input input-sm" wire:model="paymentStatusFilter" aria-label="Statut de paiement">
                    <option value="all">Tous statuts</option>
                    <option value="issued">Non payées</option>
                    <option value="partial">Partielles</option>
                </select>
                @if ($commercials->isNotEmpty())
                    <select class="input input-sm" wire:model="commercialFilter" aria-label="Commercial">
                        <option value="">Commercial</option>
                        @foreach ($commercials as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}">Factures</a>
                @if ($groups->isNotEmpty())
                    <a class="btn btn-secondary btn-sm" href="{{ $printAllUrl }}">Imprimer</a>
                    @if ($canExport)
                        <a class="btn btn-secondary btn-sm" href="{{ $pdfUrl }}">PDF</a>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="exportExcel">Excel</button>
                    @endif
                @endif
            </form>
        </div>

        @if (!$filtersApplied)
            <p class="collection-reminders-hint">Sélectionnez vos critères puis cliquez sur <strong>Appliquer</strong> pour afficher les créances échues.</p>
        @else
            <div class="collection-reminders-summary">
                <span><strong>{{ $totals['client_count'] }}</strong> client(s)</span>
                <span><strong>{{ $totals['invoice_count'] }}</strong> facture(s)</span>
                <span>Facturé <strong>{{ fmt_money($totals['total_invoiced']) }}</strong></span>
                <span>Encaissé <strong>{{ fmt_money($totals['total_paid']) }}</strong></span>
                <span class="collection-reminders-summary__due">À recouvrer <strong>{{ fmt_money($totals['total_balance']) }}</strong></span>
            </div>
        @endif
    </section>

    @if ($filtersApplied)
        @forelse ($groups as $group)
            @php
                $client = $group['client'];
                $printClientUrl = route('tenant.invoicing.collection_reminders.print', array_merge($filterQueryParams, ['client_id' => $client->id]));
            @endphp
            <section class="card app-table-card" style="margin-bottom: 16px;">
                <div class="table-toolbar">
                    <div>
                        <div class="table-title">{{ $client->name ?? '—' }}</div>
                        @if ($client->code)
                            <div class="collection-reminders-client-meta">{{ $client->code }}</div>
                        @endif
                    </div>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        @php $stat = $reminderStats[$client->id] ?? null; @endphp
                        @if ($stat)
                            <span class="badge badge-info" title="Relances déjà enregistrées">
                                {{ $stat->cnt }} relance(s)@if ($stat->last_sent) · dernière {{ \Carbon\Carbon::parse($stat->last_sent)->format('d/m/Y') }}@endif
                            </span>
                        @endif
                        <span class="badge badge-warning">Dû {{ fmt_money($group['total_balance']) }}</span>
                        <a class="btn btn-secondary btn-sm" href="{{ $printClientUrl }}">Imprimer fiche</a>
                    </div>
                </div>

                @if ($remindersAvailable)
                    <div class="collection-reminders-record">
                        <span class="collection-reminders-record__label">Enregistrer une relance :</span>
                        <select class="input input-sm" wire:model="levels.{{ $client->id }}" aria-label="Niveau de relance">
                            @foreach ($reminderLevels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select class="input input-sm" wire:model="channels.{{ $client->id }}" aria-label="Canal de relance">
                            @foreach ($reminderChannels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-primary btn-sm"
                            wire:click="recordReminder({{ $client->id }})"
                            wire:confirm="Enregistrer une relance de {{ fmt_money($group['total_balance']) }} FCFA pour {{ $client->name }} ?">
                            Enregistrer la relance
                        </button>
                    </div>
                @endif
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>N° facture</th>
                                <th>Date</th>
                                <th>Échéance</th>
                                <th>Retard</th>
                                <th>TTC</th>
                                <th>Encaissé</th>
                                <th>Solde</th>
                                <th>Réf.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['invoices'] as $row)
                                @php $inv = $row['invoice']; @endphp
                                <tr>
                                    <td><strong>{{ $inv->invoice_number }}</strong></td>
                                    <td>{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                                    <td>{{ ($row['due_date'] ?? $inv->due_date)?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge badge-error badge-sm">{{ $row['days_overdue'] }} j</span></td>
                                    <td>{{ fmt_money((float) $inv->total) }}</td>
                                    <td>{{ fmt_money((float) $inv->amount_paid) }}</td>
                                    <td><strong>{{ fmt_money((float) $inv->balance) }}</strong></td>
                                    <td class="collection-reminders-ref">
                                        @if ($inv->delivery_note_number) BL {{ $inv->delivery_note_number }} @endif
                                        @if ($inv->customer_reference) {{ $inv->customer_reference }} @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="collection-reminders-total-row">
                                <td colspan="4">Total client</td>
                                <td>{{ fmt_money($group['total_invoiced']) }}</td>
                                <td>{{ fmt_money($group['total_paid']) }}</td>
                                <td colspan="2"><strong>{{ fmt_money($group['total_balance']) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($client->phone || $client->address || $client->bp)
                    <div class="collection-reminders-client-footer">
                        @if ($client->phone) Tél. {{ $client->phone }} @endif
                        @if ($client->phone && ($client->bp || $client->address)) · @endif
                        @if ($client->bp) {{ $client->bp }} @elseif ($client->address) {{ $client->address }} @endif
                    </div>
                @endif
            </section>
        @empty
            <section class="card app-table-card">
                <p class="collection-reminders-empty">Aucune facture échue impayée ne correspond aux filtres sélectionnés.</p>
            </section>
        @endforelse
    @endif

    <style>
    .collection-reminders-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .collection-reminders-hint {
        margin: 0;
        padding: 10px 16px 14px;
        font-size: 12px;
        color: #6b7280;
        border-top: 1px solid #f3f4f6;
    }
    .collection-reminders-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 20px;
        padding: 10px 16px 14px;
        font-size: 12px;
        color: #4b5563;
        border-top: 1px solid #f3f4f6;
        background: #fffbeb;
    }
    .collection-reminders-summary__due {
        color: #b45309;
        font-weight: 600;
    }
    .collection-reminders-client-meta {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 2px;
    }
    .collection-reminders-ref {
        font-size: 11px;
        color: #6b7280;
    }
    .collection-reminders-total-row {
        background: #f9fafb;
        font-weight: 600;
        font-size: 12px;
    }
    .collection-reminders-record {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-top: 1px solid #f3f4f6;
        background: #f9fafb;
    }
    .collection-reminders-record__label {
        font-size: 12px;
        font-weight: 600;
        color: #4b5563;
    }
    .collection-reminders-client-footer {
        padding: 8px 16px 12px;
        font-size: 11px;
        color: #9ca3af;
    }
    .collection-reminders-empty {
        padding: 24px;
        text-align: center;
        font-size: 13px;
        color: #6b7280;
        margin: 0;
    }
    .badge-sm {
        font-size: 10px;
        padding: 2px 6px;
    }
    </style>
</div>
