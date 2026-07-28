<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('partials.print.document-title')
    <style>
        @include('inovcom-invoicing::print.partials.document-print-styles')
        body { padding-bottom: 90px; }
        .sheet-title { text-align:center; margin:8px 0; font-size:12px; font-weight:800; text-transform:uppercase; }
        .meta { text-align:center; font-size:10px; color:#4b5563; margin-bottom:12px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:9px; margin-bottom:12px; }
        .pay-table { width:100%; border-collapse:collapse; font-size:9px; margin-bottom:12px; }
        .pay-table th, .pay-table td { border:1px solid #111; padding:4px 6px; }
        .pay-table th { background:#f0f0f0; }
        .pay-table .amount { text-align:right; }
        .net-box { border:2px solid #111; padding:10px; text-align:center; margin-top:8px; }
        .net-box strong { font-size:18px; }
        .no-print { margin-top:20px; text-align:center; font-size:11px; }
    </style>
</head>
<body>
    <div class="page">
        @include('inovcom-invoicing::print.partials.document-header', [
            'settings' => $settings,
            'docDate' => now()->format('d/m/y'),
            'docLabel' => 'BULLETIN',
            'docNumber' => 'PAIE',
            'docSubtitle' => $periodLabel,
        ])

        <div class="sheet-title">Bulletin de paie</div>
        <div class="meta">
            <strong>{{ $employee->full_name }}</strong> — N° {{ $employee->employee_number }}
            @if ($run->reference) · {{ $run->reference }} @endif
        </div>

        <div class="info-grid">
            <div>
                <strong>Employé</strong><br>
                Poste : {{ $employee->position ?? '—' }}<br>
                Département : {{ $employee->department ?? '—' }}<br>
                CNPS : {{ $employee->cnps_number ?? '—' }}
            </div>
            <div>
                <strong>Paie</strong><br>
                Période : {{ $periodLabel }}<br>
                Banque : {{ $employee->bank_name ?? '—' }}<br>
                Compte : {{ $employee->bank_account ?? '—' }}
            </div>
        </div>

        <table class="pay-table">
            <thead>
                <tr><th>Libellé</th><th>Type</th><th class="amount">Montant (FCFA)</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->label }}</td>
                        <td>{{ $item->type_label }}</td>
                        <td class="amount">{{ fmt_money($item->amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>Salaire de base</td>
                        <td>Base</td>
                        <td class="amount">{{ fmt_money($line->base_salary) }}</td>
                    </tr>
                    @if ((float) $line->bonuses > 0)
                        <tr><td>Primes</td><td>Prime</td><td class="amount">{{ fmt_money($line->bonuses) }}</td></tr>
                    @endif
                    @if ((float) $line->deductions > 0)
                        <tr><td>Retenues</td><td>Retenue</td><td class="amount">-{{ fmt_money($line->deductions) }}</td></tr>
                    @endif
                @endforelse
            </tbody>
        </table>

        <div class="net-box">
            <div style="font-size:10px; text-transform:uppercase;">Net à payer</div>
            <strong>{{ fmt_money($line->net_salary) }} FCFA</strong>
        </div>
    </div>

    @include('inovcom-invoicing::print.partials.document-footer', ['settings' => $settings])

    @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
</body>
</html>
