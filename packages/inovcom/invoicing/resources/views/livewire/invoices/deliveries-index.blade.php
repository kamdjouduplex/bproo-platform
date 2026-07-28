<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Bons de livraison</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-secondary" href="{{ route('tenant.invoicing.index', ['tenant' => $tenantCode]) }}">Factures</a>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
            <input class="input input-sm" type="search" wire:model.live.debounce.300ms="search" placeholder="N° BL ou facture…" style="min-width:200px;">
            <select class="input input-sm" wire:model.live="status">
                <option value="">Tous statuts</option>
                <option value="draft">Brouillon</option>
                <option value="confirmed">Livré</option>
                <option value="cancelled">Annulé</option>
            </select>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>N° BL</th>
                        <th>Origine</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notes as $note)
                        <tr>
                            <td><strong>{{ $note->delivery_number }}</strong></td>
                            <td>
                                @if ($note->invoice_id)
                                    <a href="{{ route('tenant.invoicing.edit', [$note->invoice_id, 'tenant' => $tenantCode]) }}">
                                        {{ $note->invoice?->invoice_number }}
                                    </a>
                                @elseif ($note->quotation_id)
                                    <a href="{{ route('tenant.quotations.edit', ['quotation' => $note->quotation_id, 'tenant' => $tenantCode]) }}">
                                        Devis {{ $note->quotation?->number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $note->invoice?->client?->name ?? $note->client?->name ?? $note->quotation?->client?->name ?? '—' }}</td>
                            <td>{{ $note->delivery_date->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge {{ $note->isConfirmed() ? 'badge-success' : ($note->isDraft() ? 'badge-warning' : 'badge-secondary') }}">
                                    {{ \InovCom\Invoicing\Models\DeliveryNote::statusLabel($note->status) }}
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.show', ['deliveryNote' => $note->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @php
                                    $printQuery = array_merge(
                                        ['tenant' => $tenantCode],
                                        \InovCom\Invoicing\Support\DeliveryNotePrintSettings::printRouteQuery($note)
                                    );
                                @endphp
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.invoicing.deliveries.print', ['deliveryNote' => $note->id]) }}?{{ http_build_query($printQuery) }}">Imprimer</a>
                                @if ($canInvoice && $note->isConfirmed() && $note->quotation_id && !$note->invoice_id)
                                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.invoicing.create', ['tenant' => $tenantCode, 'delivery_note' => $note->id]) }}">Créer facture</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($notes->count() === 0)
                        <tr><td colspan="6" style="text-align:center;color:#9ca3af;">Aucun bon de livraison.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $notes->appends(['tenant' => $tenantCode])->links() }}</div>
    </section>
</div>
