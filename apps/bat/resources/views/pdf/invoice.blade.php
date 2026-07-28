@extends('pdf.layout')

@section('pdf-title', __('Facture') . ' ' . $invoice->code)

@section('content')
@php
    $client  = $invoice->client;
    $company = config('app.name', 'BPROO ERP');
    $docTitle = match($invoice->invoice_type ?? 'invoice') {
        'credit_note' => __('AVOIR'),
        'proforma'    => __('FACTURE PROFORMA'),
        default       => __('FACTURE'),
    };
    $statusLabel = match($invoice->status) {
        'paid'     => __('Payée'),
        'overdue'  => __('En retard'),
        'cancelled'=> __('Annulée'),
        default    => null,
    };
    $stampClass = match($invoice->status) {
        'paid'     => 'stamp--paid',
        'overdue'  => 'stamp--overdue',
        'cancelled'=> 'stamp--refused',
        default    => '',
    };
@endphp

{{-- ── Header ────────────────────────────────────────────────── --}}
<div class="pdf-header">
    <div class="pdf-header__left">
        <div class="company-name">{{ $company }}</div>
        <div class="company-info">
            {!! nl2br(e(config('pdf.company_address', ''))) !!}
        </div>
    </div>
    <div class="pdf-header__right">
        <div class="doc-title">{{ $docTitle }}</div>
        <div class="doc-code">{{ $invoice->code }}</div>
        <div class="doc-date">
            @if($invoice->issue_date)
                {{ __("Date d'émission") }}: {{ $invoice->issue_date->format('d/m/Y') }}<br>
            @endif
            @if($invoice->due_date)
                {{ __("Date d'échéance") }}: {{ $invoice->due_date->format('d/m/Y') }}
            @endif
        </div>
        @if($statusLabel)
        <div style="margin-top:8px;">
            <span class="stamp {{ $stampClass }}">{{ $statusLabel }}</span>
        </div>
        @endif
    </div>
</div>

<div class="hr"></div>

{{-- ── Address blocks ────────────────────────────────────────── --}}
<div class="address-section">
    <div class="address-block">
        <div class="address-block__title">{{ __('Émetteur') }}</div>
        <div class="address-block__name">{{ $company }}</div>
    </div>
    <div class="address-block">
        <div class="address-block__title">{{ __('Facturé à') }}</div>
        <div class="address-block__name">{{ $client?->name }}</div>
        <div class="address-block__detail">
            @if($client?->address){{ $client->address }}<br>@endif
            @if($client?->city){{ $client->city }}@if($client?->postal_code) {{ $client->postal_code }}@endif<br>@endif
            @if($client?->email){{ $client->email }}<br>@endif
            @if($client?->phone){{ $client->phone }}<br>@endif
            @if($client?->tax_id){{ __('N° fiscal') }}: {{ $client->tax_id }}@endif
        </div>
    </div>
</div>

{{-- ── Project reference ──────────────────────────────────────── --}}
@if($invoice->project)
<p style="font-size:9pt; color:#475569; margin-bottom:8px;">
    <strong>{{ __('Référence projet') }} :</strong> {{ $invoice->project->code }} — {{ $invoice->project->title }}
</p>
@endif

@if($invoice->title)
<p style="font-size:11pt; font-weight:600; color:#0f172a; margin-bottom:12px;">
    {{ __('Objet') }} : {{ $invoice->title }}
</p>
@endif

{{-- ── Line items table ──────────────────────────────────────── --}}
<table class="line-table">
    <thead>
        <tr>
            <th style="width:55%;">{{ __('Désignation') }}</th>
            <th style="width:10%;" class="text-right">{{ __('Qté') }}</th>
            <th style="width:17%;" class="text-right">{{ __('P.U. HT') }}</th>
            <th style="width:18%;" class="text-right">{{ __('Montant HT') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->lines as $line)
        <tr>
            <td>{{ $line->description }}</td>
            <td class="text-right">{{ number_format($line->quantity, 2, ',', ' ') }}</td>
            <td class="text-right">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
            <td class="text-right">{{ number_format($line->amount, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ── Totals ─────────────────────────────────────────────────── --}}
<div class="totals-wrap">
    <div class="totals-spacer"></div>
    <div class="totals-table-wrap">
        <table class="totals-table">
            <tr>
                <td class="lbl">{{ __('Total HT') }}</td>
                <td class="val">{{ number_format($invoice->total_ht, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            @if(($invoice->discount_percent ?? 0) > 0)
            <tr class="discount-row">
                <td class="lbl">{{ __('Remise') }} ({{ $invoice->discount_percent }}%)</td>
                <td class="val">– {{ number_format($invoice->discount_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ __('Net HT') }}</td>
                <td class="val">{{ number_format($invoice->net_ht, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            @endif
            @if(($invoice->tax_rate ?? 0) > 0)
            <tr>
                <td class="lbl">{{ __('TVA') }} ({{ $invoice->tax_rate }}%)</td>
                <td class="val">+ {{ number_format($invoice->tax_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="lbl">{{ __('TOTAL TTC') }}</td>
                <td class="val">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            @if(($invoice->amount_paid ?? 0) > 0)
            <tr style="color:#15803d;">
                <td class="lbl">{{ __('Encaissé') }}</td>
                <td class="val">– {{ number_format($invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</td>
            </tr>
            <tr style="{{ ($invoice->amount_due ?? 0) <= 0 ? 'color:#15803d;' : 'color:#dc2626;' }}">
                <td class="lbl"><strong>{{ __('Solde dû') }}</strong></td>
                <td class="val"><strong>{{ number_format($invoice->amount_due, 0, ',', ' ') }} {{ $invoice->currency }}</strong></td>
            </tr>
            @endif
        </table>
    </div>
</div>

{{-- ── Payment terms ───────────────────────────────────────────── --}}
@if($invoice->payment_terms)
<p style="font-size:9pt; color:#475569; margin-top:12px;">
    <strong>{{ __('Conditions de paiement') }} :</strong>
    {{ __('Paiement sous :days jours', ['days' => $invoice->payment_terms]) }}
    @if($invoice->due_date) ({{ __('au plus tard le') }} {{ $invoice->due_date->format('d/m/Y') }}) @endif
</p>
@endif

{{-- ── Notes ──────────────────────────────────────────────────── --}}
@if($invoice->notes)
<div class="notes-block" style="margin-top:14px;">
    <strong>{{ __('Notes') }} :</strong><br>
    {!! nl2br(e($invoice->notes)) !!}
</div>
@endif
@endsection

@section('footer')
    {{ $company }} — {{ $docTitle }} {{ $invoice->code }}
    @if($invoice->issue_date) — {{ __("Émise le") }} {{ $invoice->issue_date->format('d/m/Y') }} @endif
@endsection
