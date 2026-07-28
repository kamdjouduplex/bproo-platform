{{-- Styles DomPDF : pas de flexbox, marges via @page --}}
@page {
    size: A4;
    margin: 14mm 16mm;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #111;
    background: #fff;
}
.pdf-page {
    page-break-after: always;
}
.pdf-page:last-child {
    page-break-after: auto;
}
.brand-block .brand-name { font-weight: 700; font-size: 16px; }
.header-top-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}
.header-top-table td { vertical-align: top; }
.header-top-table .brand-cell { width: 58%; padding-right: 12px; }
.header-top-table .doc-cell { width: 42%; text-align: right; }
.tenant-doc-logo { max-height: 72px; max-width: 240px; }
.brand-ids { margin-top: 6px; font-size: 9.5px; line-height: 1.5; }
.brand-ids b { font-weight: 700; }
.doc-box {
    border: 2px solid #111;
    width: 100%;
    max-width: 280px;
    margin-left: auto;
    font-size: 11px;
}
.doc-box table { width: 100%; border-collapse: collapse; }
.doc-box td, .doc-box th {
    border: 1px solid #111;
    padding: 6px 8px;
    text-align: center;
}
.doc-box th { font-weight: 700; background: #f0f0f0; }
.doc-number { font-size: 13px; font-weight: 700; }
.doc-validity { padding: 4px 8px; font-size: 9px; text-align: center; border-top: 1px solid #111; }
.client-zone { text-align: right; margin: 8px 0 12px; }
.client-box {
    display: inline-block;
    border: 1px solid #111;
    padding: 8px 12px;
    width: 280px;
    font-size: 11px;
    line-height: 1.55;
    text-align: left;
}
.client-box strong { font-size: 12px; }
.client-box .client-label {
    display: block;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 9px;
    color: #555;
    margin-bottom: 2px;
}
.lines-table {
    width: 100%;
    border-collapse: collapse;
    margin: 8px 0;
    font-size: 9px;
}
.lines-table th,
.lines-table td {
    border: 1px solid #111;
    padding: 4px 5px;
    vertical-align: middle;
}
.lines-table thead th {
    background: #f0f0f0;
    font-weight: 700;
    text-align: center;
}
.lines-table td.num { text-align: right; white-space: nowrap; }
.lines-table td.qty { text-align: center; }
.lines-table td.left { text-align: left; }
.totals-wrap { text-align: right; margin-top: 8px; }
.totals-table {
    border-collapse: collapse;
    display: inline-table;
    min-width: 280px;
    font-size: 11px;
}
.totals-table td {
    border: 1px solid #111;
    padding: 6px 10px;
}
.totals-table .label { font-weight: 700; text-align: left; }
.totals-table .value { text-align: right; font-weight: 700; }
.totals-table tr.net-row .label,
.totals-table tr.net-row .value { font-size: 12px; font-weight: 700; }
.signature {
    margin-top: 36px;
    font-weight: 700;
    font-size: 11px;
    text-align: right;
}
.doc-footer {
    margin-top: 24px;
    padding-top: 8px;
    font-size: 10px;
    line-height: 1.55;
}
.doc-footer .footer-rule {
    height: 3px;
    width: 220px;
    margin-bottom: 6px;
    background: #7c3aed;
}
.doc-footer .footer-name {
    font-weight: 700;
    font-size: 12px;
    color: #4d7c0f;
    margin-bottom: 2px;
}
.doc-footer .footer-legal b,
.doc-footer .footer-contact b { font-weight: 700; }
.doc-note { margin-top: 8px; font-size: 11px; line-height: 1.45; }
