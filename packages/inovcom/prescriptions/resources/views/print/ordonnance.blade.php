<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup', ['printPageSize' => 'A4'])
        @include('partials.print.document-base-styles')
        .rx-meta { margin: 10px 0 14px; font-size: 11px; line-height: 1.5; }
        .rx-meta strong { font-weight: 700; }
        .rx-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 12px;
        }
        .rx-table th, .rx-table td {
            border: 1px solid #111;
            padding: 6px 8px;
            vertical-align: top;
        }
        .rx-table thead th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: center;
        }
        .rx-table td.num { text-align: right; white-space: nowrap; }
        .rx-table td.left { text-align: left; }
        .rx-note { margin-top: 10px; font-size: 11px; }
        .rx-status {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border: 1px solid #111;
            font-weight: 700;
            font-size: 11px;
        }
        @include('partials.item-label-css')
    </style>
</head>
<body>
    <div class="page page--last">
        <div class="print-page-inner print-page-inner--last">
            <div class="print-page-content">
                @include('partials.print.document-header', [
                    'settings' => $settings,
                    'docDate' => now()->format('d/m/y'),
                    'docLabel' => 'ORDONNANCE N°',
                    'docNumber' => $prescription->number,
                    'docSubtitle' => $prescription->dispensationStatusLabel(),
                ])

                <div class="rx-meta">
                    <div><strong>Patient :</strong> {{ $prescription->client?->name ?? '—' }}
                        @if($prescription->client?->phone) — {{ $prescription->client->phone }}@endif
                    </div>
                    @if($prescription->prescriber_name)
                        <div><strong>Prescripteur :</strong> {{ $prescription->prescriber_name }}
                            @if($prescription->prescriber_contact) ({{ $prescription->prescriber_contact }})@endif
                        </div>
                    @endif
                    <div>
                        <strong>Validité :</strong>
                        {{ $prescription->valid_from?->format('d/m/Y') ?? '—' }}
                        →
                        {{ $prescription->valid_until?->format('d/m/Y') ?? '—' }}
                    </div>
                    <div class="rx-status">{{ $prescription->dispensationStatusLabel() }}</div>
                </div>

                <table class="rx-table">
                    <thead>
                        <tr>
                            <th style="width:38%">Médicament</th>
                            <th style="width:12%">Prescrit</th>
                            <th style="width:12%">Délivré</th>
                            <th style="width:12%">Reste</th>
                            <th style="width:26%">Posologie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prescription->lines as $line)
                            <tr>
                                <td class="left">
                                    <x-item-label :reference="$line->item?->sku" :name="$line->item?->name ?? ('Article #'.$line->item_id)" />
                                </td>
                                <td class="num">{{ fmt_num((float) $line->quantity) }}</td>
                                <td class="num">{{ fmt_num((float) $line->quantity_dispensed) }}</td>
                                <td class="num"><strong>{{ fmt_num($line->remaining_quantity) }}</strong></td>
                                <td class="left">{{ $line->instructions ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($prescription->notes)
                    <div class="rx-note"><strong>Notes :</strong> {{ $prescription->notes }}</div>
                @endif
            </div>

            <div class="signature">LE PHARMACIEN</div>
            <div class="signature-space" aria-hidden="true"></div>
            <div class="print-page-footer">
                @include('partials.print.commercial-doc-footer', [
                    'settings' => $settings,
                ])
            </div>
        </div>
    </div>

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
