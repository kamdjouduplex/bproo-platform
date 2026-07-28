{{-- Styles communs devis / facture / BL : pagination explicite, notes. --}}
body { padding: 0; margin: 0; }
.page {
    margin: 0;
    padding: 0;
    max-width: 210mm;
    width: 100%;
}
.page + .page {
    page-break-before: always;
}
.print-page-inner {
    padding: 8mm 10mm 6mm;
}
.print-page-inner--last {
    min-height: 283mm;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}
.print-page-content {
    flex: 0 0 auto;
}
.print-page-footer {
    flex-shrink: 0;
    margin-top: auto;
    width: 100%;
}
.signature {
    margin-top: 130px;
    margin-bottom: 0;
    font-weight: 700;
    font-size: 11px;
    text-align: right;
    flex-shrink: 0;
}
.signature-space {
    flex: 1 1 auto;
    min-height: 40mm;
}
.print-page-footer .doc-footer {
    margin-top: 0;
}
.signatures {
    margin-top: 130px;
    flex-shrink: 0;
}
.lines-table thead { display: table-header-group; }
.lines-table tbody tr { page-break-inside: auto; break-inside: auto; }
.lines-table td { vertical-align: top; }
.doc-note { margin-top: 8px; font-size: 11px; line-height: 1.45; }
.doc-note__text { white-space: pre-wrap; word-break: break-word; }
.doc-continuation {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #111;
    font-size: 10px;
}
.doc-continuation__sep { color: #6b7280; margin: 0 4px; }
.doc-continuation__page {
    font-weight: 700;
    white-space: nowrap;
    color: #374151;
}
.doc-footer {
    position: static;
    margin-top: 10px;
    padding: 6px 0 0;
    background: #fff;
    text-align: left;
    font-size: 10px;
    line-height: 1.55;
    color: #1f2937;
}
.doc-footer--compact {
    margin-top: 8px;
    padding-top: 6px;
    border-top: 1px solid #e5e7eb;
}
.doc-page-meta {
    margin-top: 6px;
    font-size: 9px;
    color: #6b7280;
    text-align: right;
    font-weight: 600;
}
.totals-wrap,
.totals-table,
.doc-note {
    page-break-inside: avoid;
    break-inside: avoid-page;
}
.print-page-footer,
.print-page-footer .signatures {
    page-break-inside: avoid;
    break-inside: avoid-page;
}
.signature {
    page-break-inside: avoid;
    break-inside: avoid-page;
}
