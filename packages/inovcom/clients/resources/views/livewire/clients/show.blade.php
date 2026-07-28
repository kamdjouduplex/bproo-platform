@php
    $tenantCode = $tenantCode ?? request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $creditLimit = (float) $client->credit_limit;
    $outstanding = (float) $stats['outstanding'];
    $caTotal = (float) $stats['invoicedTotal'];
    $caPaid = (float) $stats['invoicedPaid'];
    $caUnpaid = (float) $stats['invoicedUnpaid'];
    $paidPct = $caTotal > 0 ? min(100, round($caPaid / $caTotal * 100)) : 0;
@endphp

<div class="page-body client-workspace">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    @include('inovcom-clients::livewire.clients.partials.workspace-header', [
        'currentView' => 'simple',
        'canUpdate' => $canUpdate,
    ])

    <section class="card" style="margin-bottom:16px;">
        @include('inovcom-clients::livewire.clients.partials.workspace-kpi')
    </section>

    <div class="client-simple-grid">
        <section class="card">
            <h3 class="card-title">Coordonnées</h3>
            <dl class="client-dl">
                <div class="client-dl__row"><dt>Email</dt><dd>{{ $client->email ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>Téléphone</dt><dd>{{ $client->phone ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>Adresse</dt><dd>{{ $client->address ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>BP</dt><dd>{{ $client->bp ?? '—' }}</dd></div>
                @if ($client->type === 'company')
                    <div class="client-dl__row"><dt>NIF / Tax ID</dt><dd>{{ $client->tax_id ?? '—' }}</dd></div>
                    <div class="client-dl__row"><dt>RCCM</dt><dd>{{ $client->rccm ?? '—' }}</dd></div>
                    <div class="client-dl__row"><dt>NIU</dt><dd>{{ $client->niu ?? '—' }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="card">
            <h3 class="card-title">Commercial</h3>
            <dl class="client-dl">
                <div class="client-dl__row"><dt>Segment</dt><dd>{{ $client->segment->name ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>Catégorie</dt><dd>{{ $client->category->name ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>Zone</dt><dd>{{ $client->zone->name ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>Commercial</dt><dd>{{ $salesrep->name ?? '—' }}</dd></div>
                <div class="client-dl__row"><dt>Palier tarifaire</dt><dd>{{ $client->priceTierLabel() }}</dd></div>
                <div class="client-dl__row"><dt>Remise par défaut</dt><dd>{{ rtrim(rtrim(number_format((float) $client->discount_rate, 2), '0'), '.') }} %</dd></div>
                <div class="client-dl__row"><dt>Condition paiement</dt><dd>{{ $paymentTerm ? $paymentTerm->name . ' (' . $paymentTerm->days . ' j)' : '—' }}</dd></div>
                <div class="client-dl__row"><dt>Mode règlement</dt><dd>{{ $client->paymentMethodLabel() }}</dd></div>
            </dl>
        </section>
    </div>

    @if ($primaryContact)
        <section class="card" style="margin-top:16px;">
            <h3 class="card-title">Contact principal</h3>
            <div class="client-contact-card">
                <div>
                    <strong>{{ $primaryContact->full_name }}</strong>
                    @if ($primaryContact->position)
                        <div style="font-size:13px;color:#64748b;">{{ $primaryContact->position }}</div>
                    @endif
                    <div style="font-size:13px;margin-top:6px;">
                        {{ $primaryContact->phone ?? $primaryContact->mobile ?? '—' }}
                        @if ($primaryContact->email) · {{ $primaryContact->email }} @endif
                    </div>
                </div>
                @if ($primaryContact->phone || $primaryContact->mobile)
                    <a class="btn btn-secondary btn-sm" href="tel:{{ $primaryContact->phone ?? $primaryContact->mobile }}">Appeler</a>
                @endif
            </div>
        </section>
    @endif

    @if ($client->notes)
        <section class="card" style="margin-top:16px;">
            <h3 class="card-title">Note fiche</h3>
            <p style="margin:0;white-space:pre-wrap;">{{ $client->notes }}</p>
        </section>
    @endif

    <section class="card client-simple-cta" style="margin-top:16px;">
        <div>
            <strong>Besoin de plus de détails ?</strong>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                Historique complet (devis, factures, relances), balance âgée, documents et journal dans la vue 360°.
            </p>
        </div>
        <div class="client-simple-cta__actions">
            <a class="btn btn-primary" href="{{ route('tenant.clients.show360', [$client->id, 'tenant' => $tenantCode]) }}">Ouvrir la vue 360°</a>
            @if (Route::has('tenant.quotations.create'))
                <a class="btn btn-secondary" href="{{ route('tenant.quotations.create', ['tenant' => $tenantCode, 'client_id' => $client->id]) }}">Nouveau devis</a>
            @endif
        </div>
    </section>

    <div class="client-simple-stats">
        <div class="client-simple-stats__item">
            <span>CA payé</span>
            <strong style="color:#166534;">{{ fmt_money($caPaid) }}</strong>
            <small>{{ $paidPct }}% encaissé</small>
        </div>
        <div class="client-simple-stats__item">
            <span>Dettes ouvertes</span>
            <strong style="color:#b91c1c;">{{ fmt_money($outstanding) }}</strong>
            <small>{{ $stats['openDebts'] }} dette(s)</small>
        </div>
        <div class="client-simple-stats__item">
            <span>CA caisse</span>
            <strong>{{ fmt_money((float) $stats['totalSales']) }}</strong>
            <small>{{ $stats['salesCount'] }} vente(s)</small>
        </div>
        <div class="client-simple-stats__item">
            <span>Dernière vente</span>
            <strong>{{ $stats['lastSaleAt'] ? \Carbon\Carbon::parse($stats['lastSaleAt'])->format('d/m/Y') : '—' }}</strong>
        </div>
    </div>
</div>
