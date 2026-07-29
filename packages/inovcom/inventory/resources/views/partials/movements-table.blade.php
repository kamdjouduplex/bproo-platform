@php
    $showItem = $showItem ?? true;
    $paginated = $paginated ?? false;
    $tenantCode = $tenantCode ?? null;
@endphp

<div class="table-scroll stock-movements-table">
    <table>
        <thead>
            <tr>
                <th>Quand</th>
                @if ($showItem)
                    <th>Article</th>
                @endif
                <th>Sens</th>
                <th>Quantité</th>
                <th>Stock / Dispo</th>
                <th>Ce qui s’est passé</th>
                <th>Document</th>
                <th>Par</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $isArray = is_array($row);
                    $createdAt = $isArray ? $row['created_at'] : $row->created_at;
                    $qty = (float) ($isArray ? $row['quantity'] : $row->quantity);
                    $direction = $isArray ? ($row['direction'] ?? null) : null;
                    $isReserveFlow = $isArray
                        ? (bool) ($row['is_reserve_flow'] ?? in_array($direction, ['reserve', 'release'], true))
                        : false;
                    $in = $direction === 'in' || (! $isReserveFlow && $qty >= 0 && $direction !== 'out');
                    $isRelease = $direction === 'release';
                    $isReserve = $direction === 'reserve';
                    $before = (float) ($isArray ? ($row['quantity_before'] ?? 0) : ($row->quantity_before ?? 0));
                    $after = (float) ($isArray ? ($row['quantity_after'] ?? 0) : ($row->quantity_after ?? 0));
                    $typeLabel = $isArray ? ($row['type_label'] ?? '—') : '—';
                    $directionLabel = $isArray ? ($row['direction_label'] ?? ($in ? 'Entrée' : 'Sortie')) : ($in ? 'Entrée' : 'Sortie');
                    $story = $isArray ? ($row['story'] ?? null) : null;
                    $refLabel = $isArray ? ($row['reference_label'] ?? null) : null;
                    $refUrl = $isArray ? ($row['reference_url'] ?? null) : null;
                    $userName = $isArray ? ($row['user_name'] ?? null) : null;
                    $reason = $isArray ? ($row['reason'] ?? null) : null;
                    $senseClass = $isReserve ? 'is-reserve' : ($isRelease ? 'is-release' : ($in ? 'is-in' : 'is-out'));
                    $qtyPrefix = $isReserve || $in ? '+' : '−';
                    $stockLabel = $isReserveFlow ? 'Dispo' : 'Stock';
                @endphp
                <tr class="stock-movements-table__row {{ $senseClass }}">
                    <td class="stock-movements-table__when">
                        <span class="stock-movements-table__date">{{ $createdAt->format('d/m/Y') }}</span>
                        <span class="stock-movements-table__time">{{ $createdAt->format('H:i') }}</span>
                    </td>
                    @if ($showItem)
                        <td>
                            @php
                                $ref = $isArray ? ($row['item_sku'] ?? null) : ($row->item?->sku ?? null);
                                $name = $isArray ? ($row['item_name'] ?? null) : ($row->item?->name ?? null);
                                $itemUrl = $isArray ? ($row['item_movements_url'] ?? null) : null;
                            @endphp
                            @if ($itemUrl)
                                <a href="{{ $itemUrl }}" class="stock-movements-table__item-link">
                                    <x-item-label :reference="$ref" :name="$name" />
                                </a>
                            @else
                                <x-item-label :reference="$ref" :name="$name" />
                            @endif
                        </td>
                    @endif
                    <td>
                        <span class="stock-movements-table__sense {{ $senseClass }}">
                            {{ $directionLabel }}
                        </span>
                        <div class="stock-movements-table__cause">{{ $typeLabel }}</div>
                    </td>
                    <td class="stock-movements-table__qty {{ $senseClass }}">
                        {{ $qtyPrefix }}{{ fmt_num(abs($qty)) }}
                        @if ($isReserveFlow)
                            <div class="stock-movements-table__qty-hint">réservé</div>
                        @endif
                    </td>
                    <td class="stock-movements-table__stock">
                        <span class="stock-movements-table__stock-label">{{ $stockLabel }}</span>
                        <span class="stock-movements-table__stock-flow" title="{{ $stockLabel }} avant → après">
                            <span>{{ fmt_num($before) }}</span>
                            <span class="stock-movements-table__arrow" aria-hidden="true">→</span>
                            <strong>{{ fmt_num($after) }}</strong>
                        </span>
                    </td>
                    <td class="stock-movements-table__story">
                        <div class="stock-movements-table__story-main">
                            {{ $story ?: ($typeLabel . ' — ' . fmt_num(abs($qty))) }}
                        </div>
                        @if ($reason && $reason !== $refLabel && ! str_starts_with((string) $reason, 'Réservation +') && ! str_starts_with((string) $reason, 'Libération'))
                            <div class="stock-movements-table__story-note">{{ $reason }}</div>
                        @endif
                    </td>
                    <td class="stock-movements-table__doc">
                        @if ($refLabel && $refUrl)
                            <a href="{{ $refUrl }}">{{ $refLabel }}</a>
                        @elseif ($refLabel)
                            {{ $refLabel }}
                        @else
                            <span class="stock-muted">Manuel / sans document</span>
                        @endif
                    </td>
                    <td>{{ $userName ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showItem ? 8 : 7 }}" class="stock-empty">
                        Aucun mouvement trouvé pour ces filtres.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($paginated && method_exists($rows, 'links'))
    <div class="table-pagination stock-page__pagination">
        {{ $rows->appends(['tenant' => $tenantCode])->links() }}
    </div>
@endif
