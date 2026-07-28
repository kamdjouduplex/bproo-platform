@include('partials.print.document-base-styles')
.doc-title-band {
    text-align: center;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    margin: 2px 0 10px;
    letter-spacing: 0.5px;
}
.doc-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 18px;
    margin-bottom: 10px;
    font-size: 11px;
}
.doc-meta-row strong { font-weight: 700; }
.currency-badge { font-weight: 700; color: #374151; }
.lines-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    font-size: 10px;
}
.lines-table th,
.lines-table td {
    border: 1px solid #111;
    padding: 5px 6px;
}
.lines-table thead th {
    background: #f0f0f0;
    font-weight: 700;
    text-align: center;
}
.lines-table td.num { text-align: right; white-space: nowrap; }
.lines-table td.qty { text-align: center; }
.lines-table td.left { text-align: left; }
@include('partials.item-label-css')
.totals-wrap { display: flex; justify-content: flex-end; margin-top: 4px; }
.totals-table {
    border-collapse: collapse;
    min-width: 300px;
    font-size: 11px;
}
.totals-table td {
    border: 1px solid #111;
    padding: 6px 12px;
}
.totals-table .label { font-weight: 700; text-align: left; white-space: nowrap; }
.totals-table .value { text-align: right; font-weight: 700; min-width: 110px; }
.totals-table tr.net-row .label,
.totals-table tr.net-row .value {
    font-size: 13px;
    font-weight: 800;
}
