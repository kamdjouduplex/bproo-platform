@php
    use App\Support\CommercialPrintPaginator;

    $bodyText = $settings['collection_reminder_body'] ?? '';
    $forPdf = $forPdf ?? false;
    $localeDate = $letterDate->locale('fr')->isoFormat('D MMMM YYYY');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.print.document-title')
    <style>
        @if ($forPdf)
            @include('partials.print.pdf-document-css')
            .letter-refs { margin: 0 0 10px; font-size: 11px; line-height: 1.6; }
            .letter-refs .subject { font-weight: 700; text-decoration: underline; }
            .letter-body { margin: 10px 0 12px; font-size: 11px; line-height: 1.65; text-align: justify; }
            .overdue-cell { font-size: 8.5px; font-weight: 600; color: #b91c1c; }
        @else
            @include('inovcom-invoicing::print.partials.document-print-styles')
            .letter-refs { margin: 0 0 10px; font-size: 11px; line-height: 1.6; }
            .letter-refs .subject { font-weight: 700; text-decoration: underline; }
            .letter-body { margin: 10px 0 12px; font-size: 11px; line-height: 1.65; text-align: justify; }
            .lines-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 8px;
                font-size: 9px;
            }
            .lines-table th,
            .lines-table td {
                border: 1px solid #111;
                padding: 4px 5px;
                text-align: center;
                vertical-align: middle;
            }
            .lines-table thead th {
                background: #f0f0f0;
                font-weight: 700;
                font-size: 8.5px;
            }
            .lines-table td.num { text-align: right; white-space: nowrap; }
            .lines-table td.left { text-align: left; }
            .overdue-cell { font-size: 8.5px; font-weight: 600; color: #b91c1c; }
            .totals-wrap { display: flex; justify-content: flex-end; margin-top: 4px; }
            .totals-table {
                border-collapse: collapse;
                min-width: 300px;
                font-size: 11px;
            }
            .totals-table td { border: 1px solid #111; padding: 6px 12px; }
            .totals-table .label { font-weight: 700; text-align: left; }
            .totals-table .value { text-align: right; font-weight: 700; min-width: 110px; }
            .totals-table tr.net-row .label,
            .totals-table tr.net-row .value { font-size: 12px; font-weight: 800; }
        @endif
    </style>
</head>
<body>
    @forelse ($groups as $group)
        @php
            $client = $group['client'];
            $letterRef = ($groups->count() === 1 && !empty($letterReference))
                ? $letterReference
                : app(\InovCom\Invoicing\Services\CollectionReminderService::class)->generateLetterReference($client->id);
            $clientLocation = $client->bp ?: $client->address;
            $clientNiu = $client->niu ?: $client->tax_id;
            $clientRc = $client->rccm ?: ($client->metadata['rc'] ?? $client->metadata['rccm'] ?? null);
            $headerVars = [
                'settings' => $settings,
                'docDate' => $letterDate->format('d/m/y'),
                'docLabel' => 'RÉFÉRENCE',
                'docNumber' => $letterRef,
                'docSubtitle' => ($letterCity ?? '') . ', le ' . $localeDate,
            ];
        @endphp

        @if ($forPdf)
            <div class="pdf-page">
                @include('partials.print.document-header-pdf', $headerVars)

                <div class="client-zone">
                    <div class="client-box">
                        <span class="client-label">Destinataire :</span>
                        <strong>{{ strtoupper($client->name ?? '') }}</strong><br>
                        @if ($client->phone){{ $client->phone }}<br>@endif
                        @if ($clientNiu){{ $clientNiu }}<br>@endif
                        @if ($clientRc){{ $clientRc }}<br>@endif
                        @if ($clientLocation){{ $clientLocation }}@endif
                    </div>
                </div>

                <div class="letter-refs">
                    <div class="subject"><strong>Objet :</strong> Relance factures impayées</div>
                </div>

                <div class="letter-body">
                    <p>Monsieur le Directeur Général,</p>
                    <p style="margin-top:10px;">
                        Nous venons par la présente, vous relancer pour nos factures impayées échues dans vos livres comptables. Dont les détails suivent.
                    </p>
                    @if ($bodyText)
                        <p style="margin-top:10px;">{{ $bodyText }}</p>
                    @endif
                </div>

                @include('inovcom-invoicing::print.partials.collection-reminder-lines-table', ['rows' => $group['invoices']])

                <div class="totals-wrap">
                    <table class="totals-table">
                        <tr>
                            <td class="label">Total facturé (échu)</td>
                            <td class="value">{{ fmt_money($group['total_invoiced']) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Total encaissé</td>
                            <td class="value">{{ fmt_money($group['total_paid']) }}</td>
                        </tr>
                        <tr class="net-row">
                            <td class="label">Total restant dû</td>
                            <td class="value">{{ fmt_money($group['total_balance']) }}</td>
                        </tr>
                    </table>
                </div>

                <div class="signature">LA DIRECTION</div>

                @include('partials.print.commercial-doc-footer', [
                    'settings' => $settings,
                    'showStamp' => false,
                ])
            </div>
        @else
            @php $printPages = CommercialPrintPaginator::pages($group['invoices'], 10, 18); @endphp

            @foreach ($printPages as $printPage)
                @php
                    $pageRows = $printPage['lines'];
                    $pageIndex = $printPage['index'];
                    $totalPrintPages = $printPage['total'];
                    $isLastPage = $pageIndex === $totalPrintPages - 1;
                @endphp
                <div class="page{{ $isLastPage ? ' page--last' : '' }}">
                    <div class="print-page-inner{{ $isLastPage ? ' print-page-inner--last' : '' }}">
                        <div class="print-page-content">
                            @if ($pageIndex === 0)
                                @include('partials.print.document-header', $headerVars)

                                <div class="client-zone">
                                    <div class="client-box">
                                        <span class="client-label">Destinataire :</span>
                                        <strong>{{ strtoupper($client->name ?? '') }}</strong><br>
                                        @if ($client->phone){{ $client->phone }}<br>@endif
                                        @if ($clientNiu){{ $clientNiu }}<br>@endif
                                        @if ($clientRc){{ $clientRc }}<br>@endif
                                        @if ($clientLocation){{ $clientLocation }}@endif
                                    </div>
                                </div>

                                <div class="letter-refs">
                                    <div class="subject"><strong>Objet :</strong> Relance factures impayées</div>
                                </div>

                                <div class="letter-body">
                                    <p>Monsieur le Directeur Général,</p>
                                    <p style="margin-top:10px;">
                                        Nous venons par la présente, vous relancer pour nos factures impayées échues dans vos livres comptables. Dont les détails suivent.
                                    </p>
                                    @if ($bodyText)
                                        <p style="margin-top:10px;">{{ $bodyText }}</p>
                                    @endif
                                </div>
                            @else
                                @include('partials.print.commercial-doc-continuation', [
                                    'docLabel' => 'RÉFÉRENCE',
                                    'docNumber' => $letterRef,
                                    'clientName' => $client->name,
                                    'pageIndex' => $pageIndex,
                                    'totalPages' => $totalPrintPages,
                                ])
                            @endif

                            @include('inovcom-invoicing::print.partials.collection-reminder-lines-table', ['rows' => $pageRows])

                            @if ($isLastPage)
                                <div class="totals-wrap">
                                    <table class="totals-table">
                                        <tr>
                                            <td class="label">Total facturé (échu)</td>
                                            <td class="value">{{ fmt_money($group['total_invoiced']) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="label">Total encaissé</td>
                                            <td class="value">{{ fmt_money($group['total_paid']) }}</td>
                                        </tr>
                                        <tr class="net-row">
                                            <td class="label">Total restant dû</td>
                                            <td class="value">{{ fmt_money($group['total_balance']) }}</td>
                                        </tr>
                                    </table>
                                </div>
                        </div>
                                <div class="signature">LA DIRECTION</div>
                                <div class="signature-space" aria-hidden="true"></div>
                                <div class="print-page-footer">
                                    @include('partials.print.commercial-doc-footer', [
                                        'settings' => $settings,
                                        'showStamp' => false,
                                        'pageIndex' => $pageIndex,
                                        'totalPages' => $totalPrintPages,
                                    ])
                                </div>
                            @else
                        </div>
                                @include('partials.print.commercial-doc-footer', [
                                    'settings' => $settings,
                                    'compact' => true,
                                    'pageIndex' => $pageIndex,
                                    'totalPages' => $totalPrintPages,
                                ])
                            @endif
                    </div>
                </div>
            @endforeach
        @endif
    @empty
        <div class="{{ $forPdf ? 'pdf-page' : 'page' }}">
            <div @unless($forPdf) class="print-page-inner" @endunless style="padding:40px; text-align:center;">
                <p>Aucune facture échue à relancer pour les critères sélectionnés.</p>
            </div>
        </div>
    @endforelse

    @if (!$forPdf)
        @include('partials.print.auto-print', ['returnUrl' => $returnUrl ?? null])
    @endif
</body>
</html>
