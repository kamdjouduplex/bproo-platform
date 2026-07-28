@extends('pdf.layout')

@section('pdf-title', __('Devis') . ' ' . $quote->code)

@section('content')
@php
    $client   = $quote->client;
    $company  = config('app.name', 'BPROO ERP');
    $statusLabel = match($quote->status) {
        'accepted' => __('Accepté'),
        'refused'  => __('Refusé'),
        default    => null,
    };
@endphp

{{-- ── Header ───────────────────────────────────────────────── --}}
<div class="pdf-header">
    <div class="pdf-header__left">
        <div class="company-name">{{ $company }}</div>
        <div class="company-info">
            {!! nl2br(e(config('pdf.company_address', ''))) !!}
        </div>
    </div>
    <div class="pdf-header__right">
        <div class="doc-title">{{ __('DEVIS') }}</div>
        <div class="doc-code">{{ $quote->code }}</div>
        <div class="doc-date">
            {{ __('Date') }}: {{ now()->format('d/m/Y') }}<br>
            @if($quote->valid_until)
                {{ __('Valable jusqu\'au') }}: {{ $quote->valid_until->format('d/m/Y') }}
            @endif
        </div>
        @if($statusLabel)
        <div style="margin-top:8px;">
            <span class="stamp stamp--{{ $quote->status }}">{{ $statusLabel }}</span>
        </div>
        @endif
    </div>
</div>

<div class="hr"></div>

{{-- ── Address blocks ───────────────────────────────────────── --}}
<div class="address-section">
    <div class="address-block">
        <div class="address-block__title">{{ __('Émetteur') }}</div>
        <div class="address-block__name">{{ $company }}</div>
    </div>
    <div class="address-block">
        <div class="address-block__title">{{ __('Destinataire') }}</div>
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

{{-- ── Title / Object ───────────────────────────────────────── --}}
@if($quote->title)
<p style="font-size:11pt; font-weight:600; color:#0f172a; margin-bottom:12px;">
    {{ __('Objet') }} : {{ $quote->title }}
</p>
@endif

{{-- ── Line items table ─────────────────────────────────────── --}}
<table class="line-table">
    <thead>
        <tr>
            <th style="width:45%;">{{ __('Désignation') }}</th>
            <th style="width:12%;" class="text-right">{{ __('Type') }}</th>
            <th style="width:10%;" class="text-right">{{ __('Qté') }}</th>
            <th style="width:8%;" class="text-right">{{ __('Unité') }}</th>
            <th style="width:14%;" class="text-right">{{ __('P.U. HT') }}</th>
            <th style="width:15%;" class="text-right">{{ __('Montant HT') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->lines as $line)
        @if(($line->line_type ?? '') === 'section')
        <tr style="background:#eef2ff;">
            <td colspan="6" style="font-weight:700; font-size:10pt; color:#3730a3; padding:10px 6px; text-transform:uppercase; letter-spacing:0.03em;">
                {{ $line->description }}
            </td>
        </tr>
        @else
        <tr>
            <td>{{ $line->description }}</td>
            <td class="text-right" style="color:#64748b; font-size:8.5pt;">{{ ucfirst($line->line_type ?? 'service') }}</td>
            <td class="text-right">{{ number_format($line->quantity, 2, ',', ' ') }}</td>
            <td class="text-right" style="color:#64748b; font-size:8.5pt;">{{ $line->unit ?: '—' }}</td>
            <td class="text-right">{{ number_format($line->unit_price, 0, ',', ' ') }}</td>
            <td class="text-right">{{ number_format($line->amount, 0, ',', ' ') }}</td>
        </tr>
        @endif
        @endforeach
    </tbody>
</table>

{{-- ── Totals ────────────────────────────────────────────────── --}}
<div class="totals-wrap">
    <div class="totals-spacer"></div>
    <div class="totals-table-wrap">
        <table class="totals-table">
            <tr>
                <td class="lbl">{{ __('Total HT') }}</td>
                <td class="val">{{ number_format($quote->total_ht, 0, ',', ' ') }} {{ $quote->currency }}</td>
            </tr>
            @if(($quote->discount_percent ?? 0) > 0)
            <tr class="discount-row">
                <td class="lbl">{{ __('Remise') }} ({{ $quote->discount_percent }}%)</td>
                <td class="val">– {{ number_format($quote->discount_amount, 0, ',', ' ') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td class="lbl">{{ __('Net HT') }}</td>
                <td class="val">{{ number_format($quote->net_ht, 0, ',', ' ') }} {{ $quote->currency }}</td>
            </tr>
            @endif
            @if(($quote->tax_rate ?? 0) > 0)
            <tr>
                <td class="lbl">{{ __('TVA') }} ({{ $quote->tax_rate }}%)</td>
                <td class="val">+ {{ number_format($quote->tax_amount, 0, ',', ' ') }} {{ $quote->currency }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="lbl">{{ __('TOTAL TTC') }}</td>
                <td class="val">{{ number_format($quote->total_ttc, 0, ',', ' ') }} {{ $quote->currency }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- ── Notes ────────────────────────────────────────────────── --}}
@if($quote->notes)
<div class="notes-block" style="margin-top:16px;">
    <strong>{{ __('Notes') }} :</strong><br>
    {!! nl2br(e($quote->notes)) !!}
</div>
@endif

{{-- ── Terms ────────────────────────────────────────────────── --}}
@if($quote->terms)
<div class="notes-block">
    <strong>{{ __('Conditions générales') }} :</strong><br>
    {!! nl2br(e($quote->terms)) !!}
</div>
@endif
@endsection

@section('footer')
    {{ $company }} — {{ __('Devis') }} {{ $quote->code }} — {{ __('Document généré le') }} {{ now()->format('d/m/Y') }}
@endsection
