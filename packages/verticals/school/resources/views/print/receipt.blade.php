<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @include('partials.print.page-setup')
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
            padding: 12mm;
        }
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }
        .toolbar a, .toolbar button {
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f2744;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        .doc {
            max-width: 180mm;
            margin: 0 auto;
            border: 1.5px solid #111;
            padding: 18px 20px;
        }
        .brand {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .tagline {
            color: #64748b;
            font-size: 11px;
            margin-bottom: 14px;
        }
        h1 {
            text-align: center;
            font-size: 15px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border-top: 1px solid #d1d5db;
            border-bottom: 2px solid #111;
            padding: 10px 0;
            margin-bottom: 16px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .row span { color: #64748b; }
        .row strong { color: #111; }
        .amount {
            margin-top: 16px;
            text-align: center;
            font-size: 22px;
            font-weight: 800;
        }
        .footer {
            margin-top: 18px;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }
        @media print {
            .toolbar { display: none !important; }
            body { padding: 6mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="primary" type="button" onclick="(window.printAndClose || window.print)()">Imprimer / PDF</button>
        @if (!empty($returnUrl))
            <a href="{{ $returnUrl }}">Retour</a>
        @endif
    </div>

    <div class="doc">
        <div class="brand">{{ $shopName }}</div>
        <div class="tagline">Reçu de paiement — Bproo School</div>
        <h1>Reçu de paiement</h1>

        <div class="row"><span>N° reçu</span><strong>{{ $receipt?->receipt_number ?? '—' }}</strong></div>
        <div class="row"><span>Étudiant</span><strong>{{ $payment->student?->student_code }} — {{ $payment->student?->first_name }} {{ $payment->student?->last_name }}</strong></div>
        <div class="row"><span>Année</span><strong>{{ $payment->academicYear?->name }}</strong></div>
        <div class="row"><span>Type</span><strong>{{ $payment->payment_type === 'bank' ? 'Banque' : 'À l’école' }}</strong></div>
        <div class="row"><span>Statut</span><strong>{{ $payment->status }}</strong></div>
        <div class="row"><span>Référence</span><strong>{{ $payment->reference ?: '—' }}</strong></div>
        <div class="row"><span>Date</span><strong>{{ optional($payment->paid_at)->format('d/m/Y H:i') ?? '—' }}</strong></div>

        <div class="amount">
            {{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency_code }}
        </div>

        <div class="footer">Document généré le {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @include('partials.print.auto-print')
</body>
</html>
