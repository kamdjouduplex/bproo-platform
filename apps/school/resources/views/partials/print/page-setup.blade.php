{{-- @page margin 0 : supprime l'espace des en-têtes/pieds du navigateur (date, URL, titre). --}}
{{-- Le padding du contenu est géré par .print-page-inner (documents commerciaux). --}}
@php($printPageSize = $printPageSize ?? 'A4')
@page {
    size: {{ $printPageSize }};
    margin: 0;
}
@media print {
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
}
