<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Mouvements de stock' }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 11px; }
        .doc-title { font-size: 16px; font-weight: bold; margin: 0 0 4px; }
        .subtitle { color: #6b7280; font-size: 10px; }
        .filters { margin-top: 8px; font-size: 10px; color: #374151; }
        .summary { margin: 10px 0 12px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; }
        td.num { text-align: right; white-space: nowrap; }
        .in { color: #15803d; font-weight: bold; }
        .out { color: #b91c1c; font-weight: bold; }
        .story { color: #374151; }
        .footer { margin-top: 12px; font-size: 9px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
    @include('partials.pdf-document-header', ['settings' => $settings ?? []])

    <div class="doc-title">{{ $title ?? 'Mouvements de stock' }}</div>
    <div class="subtitle">Document généré le {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <div class="filters">
        @if (!empty($item))
            Article : <strong>{{ $item->sku ? $item->sku . ' — ' : '' }}{{ $item->name }}</strong>
            &nbsp;|&nbsp;
        @endif
        Période :
        {{ $dateFrom ?: 'début' }}
        —
        {{ $dateTo ?: 'fin' }}
        @if (!empty($directionLabel))
            &nbsp;|&nbsp; Sens : {{ $directionLabel }}
        @endif
        @if (!empty($originLabel))
            &nbsp;|&nbsp; Origine : {{ $originLabel }}
        @endif
        @if (!empty($search))
            &nbsp;|&nbsp; Recherche : {{ $search }}
        @endif
    </div>

    <div class="summary">
        Mouvements : <strong>{{ number_format((int) ($summary['count'] ?? 0), 0, ',', ' ') }}</strong>
        &nbsp;|&nbsp;
        Entrées : <strong class="in">+{{ fmt_num((float) ($summary['total_in'] ?? 0)) }}</strong>
        &nbsp;|&nbsp;
        Sorties : <strong class="out">−{{ fmt_num((float) ($summary['total_out'] ?? 0)) }}</strong>
        @if (isset($summary['current_available']))
            &nbsp;|&nbsp;
            Stock dispo actuel : <strong>{{ fmt_num((float) $summary['current_available']) }}</strong>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                @if (empty($item))
                    <th>Réf.</th>
                    <th>Article</th>
                @endif
                <th>Sens</th>
                <th>Cause</th>
                <th>Qté</th>
                <th>Avant</th>
                <th>Après</th>
                <th>Explication</th>
                <th>Document</th>
                <th>Par</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $qty = (float) ($row['quantity'] ?? 0);
                    $direction = $row['direction'] ?? ($qty >= 0 ? 'in' : 'out');
                    $isReserveFlow = (bool) ($row['is_reserve_flow'] ?? in_array($direction, ['reserve', 'release'], true));
                    $senseLabel = $row['direction_label'] ?? ($qty >= 0 ? 'Entrée' : 'Sortie');
                    $senseClass = $direction === 'reserve' ? 'in' : ($direction === 'release' ? 'out' : ($qty >= 0 ? 'in' : 'out'));
                @endphp
                <tr>
                    <td>{{ optional($row['created_at'])->format('d/m/Y H:i') }}</td>
                    @if (empty($item))
                        <td>{{ $row['item_sku'] ?? '—' }}</td>
                        <td>{{ $row['item_name'] ?? '—' }}</td>
                    @endif
                    <td class="{{ $senseClass }}">{{ $senseLabel }}</td>
                    <td>{{ $row['type_label'] ?? '—' }}</td>
                    <td class="num {{ $senseClass }}">{{ $qty >= 0 ? '+' : '−' }}{{ fmt_num(abs($qty)) }}{{ $isReserveFlow ? ' (réservé)' : '' }}</td>
                    <td class="num">{{ fmt_num((float) ($row['quantity_before'] ?? 0)) }}</td>
                    <td class="num">{{ fmt_num((float) ($row['quantity_after'] ?? 0)) }}</td>
                    <td class="story">{{ $row['story'] ?? '—' }}</td>
                    <td>{{ $row['reference_label'] ?? '—' }}</td>
                    <td>{{ $row['user_name'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ empty($item) ? 11 : 9 }}">Aucun mouvement pour ces filtres.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $settings['shop_name'] ?? 'Inov-Com' }} — Journal des mouvements de stock
    </div>
</body>
</html>
