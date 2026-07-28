{{-- Zone fournisseur — variable : $provider --}}
<div class="client-zone">
    <div class="client-box">
        <span class="client-label">Fournisseur :</span>
        <strong>{{ $provider?->name ?? '—' }}</strong><br>
        @if ($provider?->phone){{ $provider->phone }}<br>@endif
        @if ($provider?->email){{ $provider->email }}<br>@endif
        @if ($provider?->address){{ $provider->address }}@endif
    </div>
</div>
