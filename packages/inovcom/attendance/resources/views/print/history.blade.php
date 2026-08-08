<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-invoicing::print.partials.document-print-styles')
        body { padding-bottom: 90px; }
        .page { padding-bottom: 0; }
        .sheet-title { text-align: center; margin: 8px 0 10px; font-size: 12px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .meta { text-align: center; font-size: 10px; margin-bottom: 10px; color: #4b5563; }
        .hist-table { width: 100%; border-collapse: collapse; font-size: 9px; }
        .hist-table th, .hist-table td { border: 1px solid #111; padding: 4px 6px; text-align: left; }
        .hist-table th { background: #f0f0f0; }
        .no-print { margin-top: 20px; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    <div class="page">
        @include('inovcom-invoicing::print.partials.document-header', [
            'settings' => $settings,
            'docDate' => now()->format('d/m/y'),
            'docLabel' => 'HISTORIQUE',
            'docNumber' => 'PRÉSENCE',
            'docSubtitle' => $periodLabel,
        ])

        <div class="sheet-title">Historique des pointages</div>
        <div class="meta"><strong>{{ $scopeLabel }}</strong> — {{ $periodLabel }}</div>

        <table class="hist-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Heure</th>
                    <th>Employé</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($punches as $p)
                    <tr>
                        <td>{{ $p->attendance_date->format('d/m/Y') }}</td>
                        <td>{{ $service->punchTypeLabel($p->punch_type ?? 'in') }}</td>
                        <td>{{ $p->punched_at->format('H:i:s') }}</td>
                        <td>{{ $p->employee?->full_name ?? $p->user?->name ?? '—' }}</td>
                        <td>{{ $p->source ?? 'manual' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;">Aucun pointage sur la période.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="no-print">
            <button type="button" onclick="window.print()">Imprimer / PDF</button>
        </div>
    </div>
</body>
</html>
