<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('pdf-title')</title>
<style>
/* ── Reset ─────────────────────────────────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; background: #fff; line-height: 1.5; }

/* ── Page layout ────────────────────────────────────────────────────── */
.page { padding: 20mm 15mm 20mm 15mm; }

/* ── Header ─────────────────────────────────────────────────────────── */
.pdf-header { display: table; width: 100%; margin-bottom: 24px; }
.pdf-header__left  { display: table-cell; width: 55%; vertical-align: top; }
.pdf-header__right { display: table-cell; width: 45%; vertical-align: top; text-align: right; }

.company-name { font-size: 18pt; font-weight: 700; color: #1e3a5f; }
.company-tagline { font-size: 9pt; color: #64748b; }
.company-info { font-size: 8.5pt; color: #475569; margin-top: 6px; line-height: 1.6; }

.doc-title { font-size: 22pt; font-weight: 700; color: #1e3a5f; }
.doc-code  { font-size: 11pt; color: #64748b; margin-top: 2px; }
.doc-date  { font-size: 9pt;  color: #64748b; margin-top: 6px; }

/* ── Divider ────────────────────────────────────────────────────────── */
.hr { border: none; border-top: 2px solid #1e3a5f; margin: 12px 0; }
.hr-light { border: none; border-top: 1px solid #e2e8f0; margin: 8px 0; }

/* ── Address blocks ──────────────────────────────────────────────────── */
.address-section { display: table; width: 100%; margin-bottom: 18px; }
.address-block { display: table-cell; width: 50%; vertical-align: top; padding-right: 16px; }
.address-block__title { font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-bottom: 4px; }
.address-block__name  { font-size: 11pt; font-weight: 700; color: #0f172a; }
.address-block__detail { font-size: 9pt; color: #475569; line-height: 1.6; }

/* ── Table ───────────────────────────────────────────────────────────── */
.line-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.line-table thead th {
    background: #1e3a5f;
    color: #ffffff;
    font-size: 8.5pt;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 6px 8px;
    text-align: left;
}
.line-table thead th.text-right { text-align: right; }
.line-table tbody tr { border-bottom: 1px solid #e2e8f0; }
.line-table tbody tr:nth-child(even) { background: #f8fafc; }
.line-table tbody td { padding: 5px 8px; font-size: 9.5pt; vertical-align: top; }
.line-table tbody td.text-right { text-align: right; }

/* ── Totals ──────────────────────────────────────────────────────────── */
.totals-wrap { display: table; width: 100%; }
.totals-spacer { display: table-cell; width: 55%; }
.totals-table-wrap { display: table-cell; width: 45%; vertical-align: top; }
.totals-table { width: 100%; border-collapse: collapse; }
.totals-table td { padding: 4px 8px; font-size: 9.5pt; }
.totals-table .lbl { color: #64748b; }
.totals-table .val { text-align: right; font-weight: 500; }
.totals-table .total-row td { border-top: 2px solid #1e3a5f; padding-top: 6px; font-size: 11pt; font-weight: 700; color: #1e3a5f; }
.totals-table .discount-row td { color: #dc2626; }

/* ── Notes block ─────────────────────────────────────────────────────── */
.notes-block { margin-top: 20px; padding: 10px 12px; background: #f8fafc; border-left: 3px solid #1e3a5f; font-size: 9pt; color: #475569; }
.notes-block strong { color: #1e293b; }

/* ── Footer ──────────────────────────────────────────────────────────── */
.pdf-footer {
    position: fixed;
    bottom: 10mm;
    left: 15mm;
    right: 15mm;
    font-size: 7.5pt;
    color: #94a3b8;
    text-align: center;
    border-top: 1px solid #e2e8f0;
    padding-top: 4px;
}

/* ── Status stamp ────────────────────────────────────────────────────── */
.stamp {
    display: inline-block;
    border: 3px solid currentColor;
    border-radius: 4px;
    padding: 2px 10px;
    font-size: 14pt;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .1em;
    opacity: .55;
    transform: rotate(-15deg);
}
.stamp--accepted { color: #15803d; }
.stamp--refused  { color: #dc2626; }
.stamp--paid     { color: #15803d; }
.stamp--overdue  { color: #d97706; }
.stamp-wrap { position: relative; }
.stamp-abs  { position: absolute; top: -10px; right: 20px; }
</style>
</head>
<body>
<div class="page">
    @yield('content')
</div>
<div class="pdf-footer">
    @yield('footer')
</div>
</body>
</html>
