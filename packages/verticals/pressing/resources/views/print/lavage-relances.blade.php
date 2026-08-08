<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Relances dépôt lavage') }}</title>
    <style>
        @include('partials.print.page-setup', ['printPageSize' => $printPageSize ?? 'A4'])
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 18px; }
        h1 { font-size: 16px; margin: 8px 0 6px; }
        .meta { color: #555; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border-bottom: 1px solid #ddd; padding: 6px 4px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-weight: 700; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
@include('partials.print.auto-print')

<div class="wrap">
    @include('partials.print.document-header', [
        'settings' => $settings,
        'docDate' => $docDate,
        'docLabel' => $docLabel,
        'docNumber' => $docNumber,
        'docSubtitle' => ($sinceLabel ? __('Depuis') . ' ' . $sinceLabel : null),
    ])

    <h1>{{ __('Clients à relancer (dépôt lavage absent/ancien)') }}</h1>
    <p class="meta">{{ __('Période :') }} {{ $sinceLabel }}</p>
    <p class="meta">{{ __('Nombre de clients trouvés :') }} <strong>{{ $clients->count() }}</strong></p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Client') }}</th>
                <th>{{ __('WhatsApp') }}</th>
                <th>{{ __('Dernier dépôt lavage') }}</th>
                <th>{{ __('Commande') }}</th>
                <th>{{ __('Agence') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($clients as $client)
                @php
                    $fullName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                    $lastAt = $client->last_lavage_at ? \Carbon\Carbon::parse($client->last_lavage_at)->startOfDay() : null;
                    $days = $lastAt ? (int) $lastAt->diffInDays(now()->startOfDay()) : null;
                    $daysLabel = $days === null
                        ? __('Jamais')
                        : ($days === 0
                            ? __('Aujourd’hui')
                            : trans_choice(':count jour|:count jours', $days, ['count' => $days]));
                    $dateHint = $lastAt ? $lastAt->format('d/m/Y') : null;
                @endphp
                <tr>
                    <td>{{ $client->code }}</td>
                    <td>{{ $fullName ?: '—' }}</td>
                    <td>{{ $client->whatsapp ?: '—' }}</td>
                    <td>
                        <strong>{{ $daysLabel }}</strong>
                        @if ($dateHint)
                            <div style="font-size:11px;color:#555;">{{ $dateHint }}</div>
                        @endif
                    </td>
                    <td>{{ $client->last_order_number ?: '—' }}</td>
                    <td>{{ $client->agence_name ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
</body>
</html>

