{{-- Styles communs en-tête / pied de page (navigateur + impression) --}}
@include('partials.print.page-setup')
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: "Segoe UI", Tahoma, Arial, sans-serif;
    font-size: 11px;
    color: #111;
    background: #fff;
}
@media screen {
    body { background: #f3f4f6; }
    .page {
        margin: 16px auto;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }
}
.page { margin: 0; padding: 0; max-width: 210mm; width: 100%; }
.page + .page { page-break-before: always; }
.brand-block .brand-name { font-weight: 800; font-size: 16px; }
.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.brand-block { flex: 1; text-align: left; padding-right: 24px; }
.tenant-doc-logo { max-height: 80px; max-width: 260px; object-fit: contain; }
.brand-ids { margin-top: 6px; font-size: 9.5px; line-height: 1.5; color: #1f2937; }
.brand-ids b { font-weight: 700; }
.doc-box {
    border: 2px solid #111;
    width: 300px;
    font-size: 11px;
}
.doc-box table { width: 100%; border-collapse: collapse; }
.doc-box td, .doc-box th {
    border: 1px solid #111;
    padding: 6px 10px;
    text-align: center;
}
.doc-box th { font-weight: 700; background: #f8f8f8; }
.doc-number { font-size: 14px; font-weight: 800; letter-spacing: 0.5px; }
.doc-validity { padding: 4px 8px; font-size: 9px; text-align: center; border-top: 1px solid #111; }
.client-zone {
    display: flex;
    justify-content: flex-end;
    margin: 8px 0 12px;
}
.client-box {
    border: 1px solid #111;
    padding: 8px 12px;
    width: 300px;
    font-size: 11px;
    line-height: 1.55;
}
.client-box strong { font-size: 12px; }
.client-box .client-label {
    display: block;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 9px;
    letter-spacing: .5px;
    color: #555;
    margin-bottom: 2px;
}
@include('partials.print.commercial-document-css')
.doc-footer .print-stamp {
    margin-top: 4px;
    font-size: 8.5px;
    color: #6b7280;
    font-weight: 700;
}
.doc-footer .print-stamp strong,
.doc-footer .print-stamp b { font-weight: 800; }
.doc-footer .footer-rule {
    height: 3px;
    width: 230px;
    margin-bottom: 6px;
    background: linear-gradient(90deg, #7c3aed 0%, #7c3aed 55%, #65a30d 55%, #65a30d 100%);
    border-radius: 2px;
}
.doc-footer .footer-name {
    font-weight: 800;
    font-size: 12px;
    color: #4d7c0f;
    letter-spacing: .2px;
    margin-bottom: 2px;
}
.doc-footer .footer-legal,
.doc-footer .footer-contact { color: #1f2937; }
.doc-footer .footer-legal b,
.doc-footer .footer-contact b { font-weight: 700; }
.doc-footer .footer-sep { display: inline-block; width: 10px; }
.no-print { margin-top: 20px; text-align: center; color: #666; font-size: 11px; }
@media print {
    .no-print { display: none !important; }
}
