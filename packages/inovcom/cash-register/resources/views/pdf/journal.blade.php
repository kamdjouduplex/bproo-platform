<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal de caisse</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; }
        .doc-title { font-size: 18px; font-weight: bold; margin: 0 0 4px; }
        .subtitle { color: #6b7280; font-size: 11px; }
        .filters { margin-top: 8px; font-size: 11px; color: #374151; }
        .summary { margin: 12px 0 14px; padding: 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; }
        td.num { text-align: right; }
        .footer { margin-top: 12px; font-size: 10px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
    @include('partials.pdf-document-header', ['settings' => $settings ?? []])

    <div class="doc-title">{{ $title ?? 'Journal de caisse' }}</div>
    <div class="subtitle">Document exporté le {{ $generatedAt->format('d/m/Y H:i') }}</div>
    <div class="filters">
        Période :
        {{ $dateFrom ?: 'début' }}
        —
        {{ $dateTo ?: 'fin' }}
        @if (!empty($search))
            | Recherche : {{ $search }}
        @endif
    </div>

    @php
        $totalIn = (float) $entries->where('direction', 'in')->sum('amount');
        $totalOut = (float) $entries->where('direction', 'out')->sum('amount');
        $lastBalance = (float) ($entries->first()->balance_after ?? 0);
    @endphp

    <div class="summary">
        Entrées : <strong>{{ fmt_money($totalIn) }} FCFA</strong>
        &nbsp; | &nbsp;
        Sorties : <strong>{{ fmt_money($totalOut) }} FCFA</strong>
        &nbsp; | &nbsp;
        Solde actuel : <strong>{{ fmt_money($lastBalance) }} FCFA</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Motif</th>
                <th>Référence</th>
                <th>Entrée</th>
                <th>Sortie</th>
                <th>Solde</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->entry_date?->format('d/m/Y H:i') }}</td>
                    <td>{{ $entry->type_label }}</td>
                    <td>{{ $entry->reason }}</td>
                    <td>{{ $entry->reference_number ?: '—' }}</td>
                    <td class="num">{{ $entry->direction === 'in' ? fmt_money((float) $entry->amount) : '—' }}</td>
                    <td class="num">{{ $entry->direction === 'out' ? fmt_money((float) $entry->amount) : '—' }}</td>
                    <td class="num">{{ fmt_money((float) $entry->balance_after) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucun mouvement trouvé pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $settings['shop_name'] ?? 'Inov-Com' }} — Caisse
    </div>
</body>
</html>
