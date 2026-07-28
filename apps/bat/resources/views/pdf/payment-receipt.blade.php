@extends('pdf.layout')

@section('pdf-title', __('Reçu de paiement') . ' ' . $receiptCode)

@section('content')
@php
    $client  = $invoice->client;
    $company = config('app.name', 'BPROO ERP');
@endphp

<div class="pdf-header">
    <div class="pdf-header__left">
        <div class="company-name">{{ $company }}</div>
        <div class="company-info">
            {!! nl2br(e(config('pdf.company_address', ''))) !!}
        </div>
    </div>
    <div class="pdf-header__right">
        <div class="doc-title">{{ __('REÇU DE PAIEMENT') }}</div>
        <div class="doc-code">{{ $receiptCode }}</div>
        <div class="doc-date" style="margin-top:6px;">
            {{ __('Accusé de réception de paiement') }}<br>
            {{ __('Émis le') }} {{ now()->format('d/m/Y') }}
        </div>
    </div>
</div>

<div class="hr"></div>

<div class="address-section">
    <div class="address-block">
        <div class="address-block__title">{{ __('Émetteur') }}</div>
        <div class="address-block__name">{{ $company }}</div>
    </div>
    <div class="address-block">
        <div class="address-block__title">{{ __('Reçu de') }}</div>
        <div class="address-block__name">{{ $client?->name }}</div>
        <div class="address-block__detail">
            @if($client?->address){{ $client->address }}<br>@endif
            @if($client?->city){{ $client->city }}@if($client?->postal_code) {{ $client->postal_code }}@endif<br>@endif
            @if($client?->email){{ $client->email }}<br>@endif
            @if($client?->phone){{ $client->phone }}@endif
        </div>
    </div>
</div>

<p style="font-size:10pt; color:#334155; margin-bottom:14px; line-height:1.6;">
    {{ __('Nous accusons réception de la somme ci-dessous, au titre du règlement de la facture :code.', ['code' => $invoice->code]) }}
    @if($invoice->title)
        <br><strong>{{ __('Objet') }} :</strong> {{ $invoice->title }}
    @endif
</p>

<table class="line-table">
    <thead>
        <tr>
            <th>{{ __('Détail du paiement') }}</th>
            <th class="text-right" style="width:35%;">{{ __('Valeur') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ __('Date de paiement') }}</td>
            <td class="text-right">{{ $payment->payment_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>{{ __('Montant encaissé') }}</td>
            <td class="text-right"><strong>{{ number_format($payment->amount, 0, ',', ' ') }} {{ $invoice->currency }}</strong></td>
        </tr>
        <tr>
            <td>{{ __('Moyen de paiement') }}</td>
            <td class="text-right">{{ $payment->paymentMethodLabel() }}</td>
        </tr>
        <tr>
            <td>{{ __('Référence') }}</td>
            <td class="text-right">{{ $payment->reference ?: '—' }}</td>
        </tr>
        <tr>
            <td>{{ __('Facture concernée') }}</td>
            <td class="text-right">{{ $invoice->code }}</td>
        </tr>
        @if($invoice->issue_date)
        <tr>
            <td>{{ __("Date d'émission facture") }}</td>
            <td class="text-right">{{ $invoice->issue_date->format('d/m/Y') }}</td>
        </tr>
        @endif
        @if($payment->recordedByUser)
        <tr>
            <td>{{ __('Enregistré par') }}</td>
            <td class="text-right">{{ $payment->recordedByUser->name }}</td>
        </tr>
        @endif
    </tbody>
</table>

<div class="totals-wrap">
    <div class="totals-spacer"></div>
    <div class="totals-table-wrap">
        <table class="totals-table">
            <tr>
                <td class="lbl">{{ __('Total facture TTC') }}</td>
                <td class="val">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            <tr style="color:#15803d;">
                <td class="lbl">{{ __('Total encaissé à ce jour') }}</td>
                <td class="val">{{ number_format($cumulativePaid, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            <tr class="total-row">
                <td class="lbl">{{ __('Solde restant') }}</td>
                <td class="val" style="color:{{ $balanceAfter <= 0 ? '#15803d' : '#dc2626' }};">
                    {{ number_format(max(0, $balanceAfter), 0, ',', ' ') }} {{ $invoice->currency }}
                </td>
            </tr>
        </table>
    </div>
</div>

@if($balanceAfter <= 0)
<p style="margin-top:16px; font-size:10pt; color:#15803d; font-weight:600;">
    {{ __('Facture intégralement réglée.') }}
</p>
@endif

<div class="notes-block" style="margin-top:20px;">
    <strong>{{ __('Mention légale') }} :</strong><br>
    {{ __('Ce document atteste de la réception du paiement indiqué ci-dessus. Il ne se substitue pas à la facture originale et doit être conservé comme justificatif de règlement.') }}
</div>

<div style="margin-top:36px; display:table; width:100%;">
    <div style="display:table-cell; width:50%; vertical-align:top;">
        <div style="font-size:8pt; color:#64748b; text-transform:uppercase; letter-spacing:.06em; margin-bottom:28px;">
            {{ __('Signature client') }}
        </div>
        <div style="border-bottom:1px solid #cbd5e1; width:80%; height:32px;"></div>
    </div>
    <div style="display:table-cell; width:50%; vertical-align:top; text-align:right;">
        <div style="font-size:8pt; color:#64748b; text-transform:uppercase; letter-spacing:.06em; margin-bottom:28px;">
            {{ __('Cachet et signature') }}
        </div>
        <div style="border-bottom:1px solid #cbd5e1; width:80%; height:32px; margin-left:auto;"></div>
    </div>
</div>
@endsection

@section('footer')
    {{ $company }} — {{ __('Reçu de paiement') }} {{ $receiptCode }} — {{ __('Facture') }} {{ $invoice->code }}
@endsection
