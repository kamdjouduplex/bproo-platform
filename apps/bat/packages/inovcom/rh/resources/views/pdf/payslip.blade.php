<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bulletin de paie – {{ $payslip->periodLabel() }}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9.5pt;
    color: #1e293b;
    background: #fff;
    line-height: 1.55;
}

.page { padding: 16mm 14mm 20mm 14mm; }

/* ── Header ──────────────────────────────────────────────── */
.header { display: table; width: 100%; margin-bottom: 14px; }
.header__left  { display: table-cell; width: 55%; vertical-align: top; }
.header__right { display: table-cell; width: 45%; vertical-align: top; text-align: right; }
.company-name  { font-size: 17pt; font-weight: 700; color: #1e3a5f; }
.company-sub   { font-size: 8pt; color: #64748b; margin-top: 2px; }
.doc-title     { font-size: 18pt; font-weight: 700; color: #1e3a5f; text-transform: uppercase; letter-spacing: .04em; }
.doc-period    { font-size: 10pt; color: #475569; margin-top: 3px; font-weight: 600; }
.doc-ref       { font-size: 8pt; color: #94a3b8; margin-top: 2px; }

/* ── Dividers ────────────────────────────────────────────── */
.hr-thick { border: none; border-top: 2.5px solid #1e3a5f; margin: 10px 0; }
.hr-thin  { border: none; border-top: 1px solid #e2e8f0;   margin: 8px 0; }

/* ── Employee / contract info block ─────────────────────── */
.info-section { display: table; width: 100%; margin-bottom: 14px; }
.info-col     { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
.info-col:last-child { padding-right: 0; padding-left: 12px; }
.info-label   { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; margin-bottom: 5px; }
.info-name    { font-size: 12pt; font-weight: 700; color: #0f172a; }
.info-row     { display: table; width: 100%; margin-top: 3px; }
.info-row__k  { display: table-cell; font-size: 8.5pt; color: #64748b; width: 40%; }
.info-row__v  { display: table-cell; font-size: 8.5pt; font-weight: 600; color: #1e293b; }

/* ── Salary table ────────────────────────────────────────── */
.salary-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }

.salary-table .section-header td {
    background: #1e3a5f;
    color: #ffffff;
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 5px 10px;
}
.salary-table .section-header td.right { text-align: right; }

.salary-table .row-base td {
    padding: 6px 10px;
    font-size: 9.5pt;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.salary-table .row-item td {
    padding: 4px 10px;
    font-size: 9pt;
    border-bottom: 1px solid #f1f5f9;
}
.salary-table .row-item td.deduction { color: #dc2626; }
.salary-table .row-item td.addition  { color: #15803d; }

.salary-table .row-subtotal td {
    padding: 6px 10px;
    font-size: 9.5pt;
    font-weight: 700;
    background: #e2e8f0;
    color: #1e3a5f;
    border-top: 1.5px solid #cbd5e1;
    border-bottom: 1.5px solid #cbd5e1;
}
.salary-table .row-net td {
    padding: 10px 10px;
    font-size: 14pt;
    font-weight: 700;
    color: #ffffff;
    background: #1e3a5f;
}

.text-right { text-align: right; }
.text-left  { text-align: left; }

/* ── Signature block ─────────────────────────────────────── */
.signature-section { display: table; width: 100%; margin-top: 28px; }
.signature-col     { display: table-cell; width: 48%; vertical-align: top; text-align: center; }
.signature-spacer  { display: table-cell; width: 4%; }
.signature-label   { font-size: 8.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: 40px; }
.signature-line    { border-top: 1px solid #94a3b8; margin-top: 50px; padding-top: 4px; }
.signature-name    { font-size: 8pt; color: #94a3b8; }

/* ── Paid stamp ──────────────────────────────────────────── */
.stamp-paid {
    display: inline-block;
    border: 3px solid #15803d;
    border-radius: 4px;
    padding: 2px 10px;
    font-size: 13pt;
    font-weight: 900;
    color: #15803d;
    text-transform: uppercase;
    letter-spacing: .12em;
    opacity: .5;
}

/* ── Footer ──────────────────────────────────────────────── */
.pdf-footer {
    position: fixed;
    bottom: 10mm;
    left: 14mm;
    right: 14mm;
    font-size: 7pt;
    color: #94a3b8;
    text-align: center;
    border-top: 1px solid #e2e8f0;
    padding-top: 4px;
}
</style>
</head>
<body>

<div class="page">
@php
    $additions  = collect($payslip->additions ?? []);
    $deductions = collect($payslip->deductions ?? []);
    $addTotal   = $additions->sum(fn($r)  => (float)($r['amount'] ?? 0));
    $dedTotal   = $deductions->sum(fn($r) => (float)($r['amount'] ?? 0));
    $gross      = (float) $payslip->gross_salary;
    $net        = (float) $payslip->net_salary;
    $currency   = 'FCFA';
@endphp

{{-- ── Header ────────────────────────────────────────────────── --}}
<div class="header">
    <div class="header__left">
        <div class="company-name">{{ $companyName }}</div>
        <div class="company-sub">Bulletin de Salaire</div>
    </div>
    <div class="header__right">
        <div class="doc-title">Bulletin de Paie</div>
        <div class="doc-period">{{ $payslip->periodLabel() }}</div>
        <div class="doc-ref">Réf. {{ $payslip->code }}</div>
        @if($payslip->status === 'paid')
        <div style="margin-top:6px;">
            <span class="stamp-paid">Payé</span>
        </div>
        @endif
    </div>
</div>

<div class="hr-thick"></div>

{{-- ── Employee + Contract info ────────────────────────────────── --}}
<div class="info-section">

    <div class="info-col">
        <div class="info-label">Employé</div>
        <div class="info-name">{{ $employee->fullName() }}</div>
        @if($employee->position)
        <div class="info-row">
            <span class="info-row__k">Poste</span>
            <span class="info-row__v">{{ $employee->position }}</span>
        </div>
        @endif
        @if($employee->department)
        <div class="info-row">
            <span class="info-row__k">Service</span>
            <span class="info-row__v">{{ $employee->department }}</span>
        </div>
        @endif
    </div>

    <div class="info-col">
        <div class="info-label">Contrat</div>
        <div class="info-row">
            <span class="info-row__k">Type</span>
            <span class="info-row__v">{{ $employee->contractLabel() }}</span>
        </div>
        <div class="info-row">
            <span class="info-row__k">Embauché le</span>
            <span class="info-row__v">{{ $employee->hire_date->format('d/m/Y') }}</span>
        </div>
        @if($payslip->paid_at)
        <div class="info-row">
            <span class="info-row__k">Payé le</span>
            <span class="info-row__v">{{ $payslip->paid_at->format('d/m/Y') }}</span>
        </div>
        @endif
    </div>

</div>

<div class="hr-thin"></div>

{{-- ── Salary table ─────────────────────────────────────────────── --}}
<table class="salary-table">

    {{-- Column headers --}}
    <tr class="section-header">
        <td class="text-left" style="width:70%;">Libellé</td>
        <td class="right" style="width:30%;">Montant ({{ $currency }})</td>
    </tr>

    {{-- Base salary --}}
    <tr class="row-base">
        <td>Salaire de base</td>
        <td class="text-right">{{ number_format((float)$payslip->base_salary, 0, ',', ' ') }}</td>
    </tr>

    {{-- Additions section --}}
    @if($additions->isNotEmpty())
    <tr class="section-header">
        <td colspan="2" style="font-size:7.5pt; background:#2d4f7c; padding:4px 10px;">
            Primes &amp; Compléments
        </td>
    </tr>
    @foreach($additions as $row)
    @if(!empty($row['label']))
    <tr class="row-item">
        <td class="addition">+ {{ $row['label'] }}</td>
        <td class="text-right addition">+ {{ number_format((float)($row['amount'] ?? 0), 0, ',', ' ') }}</td>
    </tr>
    @endif
    @endforeach
    @endif

    {{-- Gross total --}}
    <tr class="row-subtotal">
        <td>Salaire Brut</td>
        <td class="text-right">{{ number_format($gross, 0, ',', ' ') }}</td>
    </tr>

    {{-- Deductions section --}}
    @if($deductions->isNotEmpty())
    <tr class="section-header">
        <td colspan="2" style="font-size:7.5pt; background:#7f1d1d; padding:4px 10px;">
            Retenues &amp; Déductions
        </td>
    </tr>
    @foreach($deductions as $row)
    @if(!empty($row['label']))
    <tr class="row-item">
        <td class="deduction">− {{ $row['label'] }}</td>
        <td class="text-right deduction">− {{ number_format((float)($row['amount'] ?? 0), 0, ',', ' ') }}</td>
    </tr>
    @endif
    @endforeach
    @endif

    {{-- Net --}}
    <tr class="row-net">
        <td class="text-left">NET À PAYER</td>
        <td class="text-right">{{ number_format($net, 0, ',', ' ') }} {{ $currency }}</td>
    </tr>

</table>

{{-- ── Notes ───────────────────────────────────────────────────── --}}
@if($payslip->notes)
<div style="margin-top:14px; padding:8px 10px; background:#f8fafc; border-left:3px solid #1e3a5f; font-size:8.5pt; color:#475569;">
    <strong style="color:#1e293b;">Notes :</strong> {{ $payslip->notes }}
</div>
@endif

{{-- ── Signatures ───────────────────────────────────────────────── --}}
<div class="signature-section">
    <div class="signature-col">
        <div class="signature-label">Signature de l'Employeur</div>
        <div class="signature-line">
            <div class="signature-name">{{ $companyName }}</div>
        </div>
    </div>
    <div class="signature-spacer"></div>
    <div class="signature-col">
        <div class="signature-label">Signature de l'Employé</div>
        <div class="signature-line">
            <div class="signature-name">{{ $employee->fullName() }}</div>
        </div>
    </div>
</div>

</div>{{-- end .page --}}

<div class="pdf-footer">
    {{ $companyName }} &mdash; {{ $employee->fullName() }} &mdash; {{ $payslip->periodLabel() }} &mdash; {{ $payslip->code }}
    &mdash; Document généré le {{ now()->format('d/m/Y') }}
</div>

</body>
</html>
