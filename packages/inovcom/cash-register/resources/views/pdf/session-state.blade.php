<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>État de caisse — {{ $session->session_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; }
        .doc-title { font-size: 18px; font-weight: bold; margin: 0 0 4px; }
        .subtitle { color: #6b7280; font-size: 11px; margin-bottom: 12px; }
        .meta-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #e5e7eb; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; width: 50%; vertical-align: top; }
        .meta-label { font-size: 10px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.04em; }
        .meta-value { font-size: 13px; font-weight: 600; margin-top: 2px; }
        .summary { margin: 12px 0 14px; padding: 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
        .summary strong { color: #0f172a; }
        .variance-ok { color: #16a34a; }
        .variance-bad { color: #dc2626; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; }
        td.num { text-align: right; }
        .footer { margin-top: 12px; font-size: 10px; color: #6b7280; text-align: right; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .badge-open { background: #fef3c7; color: #92400e; }
        .badge-closed { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>
    @include('partials.pdf-document-header', ['settings' => $settings ?? []])

    <div class="doc-title">État de caisse</div>
    <div class="subtitle">
        Session {{ $session->session_number }}
        · Généré le {{ $generatedAt->format('d/m/Y H:i') }}
        ·
        @if ($session->status === 'open')
            <span class="badge badge-open">Ouverte</span>
        @else
            <span class="badge badge-closed">Clôturée</span>
        @endif
    </div>

    <div class="meta-grid">
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">Ouverture</div>
                <div class="meta-value">{{ $session->opened_at?->format('d/m/Y H:i') ?? '—' }}</div>
                <div style="font-size:11px;color:#6b7280;margin-top:4px;">
                    Par : {{ $session->opener?->name ?? '—' }}
                </div>
            </div>
            <div class="meta-cell">
                <div class="meta-label">Fond de caisse initial</div>
                <div class="meta-value">{{ fmt_money((float) $session->opening_amount) }} FCFA</div>
            </div>
        </div>
        <div class="meta-row">
            <div class="meta-cell">
                <div class="meta-label">Clôture</div>
                <div class="meta-value">{{ $session->closed_at?->format('d/m/Y H:i') ?? '—' }}</div>
                @if ($session->closer)
                    <div style="font-size:11px;color:#6b7280;margin-top:4px;">Par : {{ $session->closer->name }}</div>
                @endif
            </div>
            <div class="meta-cell">
                <div class="meta-label">Mouvements enregistrés</div>
                <div class="meta-value">{{ $summary['movement_count'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="summary">
        Total entrées : <strong>{{ fmt_money((float) ($summary['total_in'] ?? 0)) }} FCFA</strong>
        &nbsp;|&nbsp;
        Total sorties : <strong>{{ fmt_money((float) ($summary['total_out'] ?? 0)) }} FCFA</strong>
        &nbsp;|&nbsp;
        Solde théorique : <strong>{{ fmt_money((float) ($summary['expected_balance'] ?? 0)) }} FCFA</strong>
        @if ($session->status === 'closed' && $session->closing_amount_counted !== null)
            &nbsp;|&nbsp;
            Montant compté : <strong>{{ fmt_money((float) $session->closing_amount_counted) }} FCFA</strong>
            @if ($variance !== null)
                &nbsp;|&nbsp;
                Écart :
                <strong class="{{ abs($variance) < 0.01 ? 'variance-ok' : 'variance-bad' }}">
                    {{ fmt_money($variance) }} FCFA
                </strong>
            @endif
        @endif
    </div>

    @if ($session->close_note)
        <p style="font-size:11px;color:#374151;margin:0 0 12px;">
            <strong>Note de clôture :</strong> {{ $session->close_note }}
        </p>
    @endif

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
                    <td colspan="7">Aucun mouvement manuel enregistré pour cette session.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $settings['shop_name'] ?? 'Inov-Com' }} — État de caisse {{ $session->session_number }}
    </div>
</body>
</html>
