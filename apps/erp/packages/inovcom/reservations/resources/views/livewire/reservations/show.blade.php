@php $tenantCode = $tenantCode ?? request()->query('tenant'); @endphp
<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <section class="card" style="padding:20px; margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
            <div>
                <h2 style="margin:0 0 6px;">{{ $reservation->reference }}</h2>
                <p style="margin:0; color:#6b7280;">
                    Client : <strong>{{ $reservation->client?->name ?? '—' }}</strong>
                    · Créée par <strong>{{ $reservation->creator?->name ?? '—' }}</strong>
                    le {{ $reservation->created_at->format('d/m/Y H:i') }}
                </p>
                <p style="margin:8px 0 0; color:#6b7280; font-size:13px;">
                    Réservation : {{ $reservation->reservation_date->format('d/m/Y') }}
                    @if ($reservation->expected_date)
                        · Retrait prévu : {{ $reservation->expected_date->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <span class="badge badge-secondary">{{ $reservation->status_label }}</span>
                @if ($reservation->quotation_id && \Illuminate\Support\Facades\Route::has('tenant.quotations.edit'))
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.quotations.edit', [$reservation->quotation_id, 'tenant' => $tenantCode]) }}">Voir le devis</a>
                @endif
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.reservations.index', ['tenant' => $tenantCode]) }}">← Réservations</a>
            </div>
        </div>
        @if ($reservation->notes)
            <p style="margin:12px 0 0; font-size:14px;">{{ $reservation->notes }}</p>
        @endif
    </section>

    <section class="card app-table-card" style="margin-bottom:16px;">
        <div class="table-title" style="padding:12px 16px 0;">Lignes réservées</div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Réservé</th>
                        <th>Annulé</th>
                        <th>Actif</th>
                        <th>P.U.</th>
                        <th>Total actif</th>
                        @if ($reservation->isActive() && $canCancel)
                            <th>Libérer</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reservation->lines as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td><x-item-label :reference="$line->item_sku" :name="$line->item_name" /></td>
                            <td>{{ fmt_num($line->quantity) }}</td>
                            <td>{{ fmt_num($line->quantity_cancelled) }}</td>
                            <td><strong>{{ fmt_num($line->active_quantity) }}</strong></td>
                            <td>{{ fmt_money($line->unit_price) }}</td>
                            <td>{{ fmt_money($line->active_quantity * (float) $line->unit_price) }}</td>
                            @if ($reservation->isActive() && $canCancel)
                                <td>
                                    @if ($line->active_quantity > 0)
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <input class="input input-sm" type="number" step="0.001" min="0.001" max="{{ $line->active_quantity }}" wire:model="cancelQty.{{ $line->id }}" style="width:80px;" placeholder="Qté">
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelLine({{ $line->id }})">Libérer</button>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($reservation->isActive())
        @if ($canUpdate)
            <section class="card" style="padding:20px; margin-bottom:16px;">
                <h3 class="form-section-title">Ajouter un article</h3>
                <div style="position:relative;">
                    <input
                        class="input"
                        wire:model.live.debounce.200ms="itemSearch"
                        placeholder="{{ item_search_placeholder() }}"
                        autocomplete="off"
                    >
                    @if (strlen(trim($itemSearch)) >= 1)
                        <div style="position:absolute; z-index:40; left:0; right:0; top:calc(100% + 4px); max-height:260px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:8px; background:#fff; box-shadow:0 8px 24px rgba(15,23,42,.12);">
                            @forelse ($searchResults as $item)
                                <button
                                    type="button"
                                    wire:click.prevent="addLine({{ $item['id'] }})"
                                    style="display:flex; justify-content:space-between; gap:12px; width:100%; padding:10px 12px; border:none; border-bottom:1px solid #f1f5f9; background:#fff; cursor:pointer; text-align:left;"
                                >
                                    <span>
                                        <x-item-label :reference="$item['sku'] ?? null" :name="$item['name'] ?? null" />
                                        <span style="display:block; color:#6b7280; font-size:12px; margin-top:2px;">
                                            Dispo {{ $item['available_qty'] !== null ? fmt_num($item['available_qty']) : '—' }}
                                        </span>
                                    </span>
                                    <span style="white-space:nowrap; font-weight:600;">{{ fmt_money($item['price']) }}</span>
                                </button>
                            @empty
                                <div style="padding:12px; color:#6b7280; font-size:13px;">Aucun article trouvé.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <div class="page-actions">
            @if ($canConvert)
                <button type="button" class="btn btn-primary" wire:click="convertToQuotation" onclick="return confirm('Convertir en devis ? Le stock reste réservé (transféré sur le devis).')">Convertir en devis</button>
            @endif
            @if ($canCancel)
                <button type="button" class="btn btn-error" wire:click="cancelAll" onclick="return confirm('Annuler toute la réservation et restaurer le stock ?')">Annuler la réservation</button>
            @endif
        </div>
    @endif
</div>
