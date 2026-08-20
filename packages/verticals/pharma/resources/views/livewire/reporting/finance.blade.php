<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')
    @include('pharma::livewire.reporting.partials.page-head', [
        'code' => 'RP0501',
        'title' => 'Finance',
        'lead' => 'Encaissements, dépenses, pertes et créances.',
        'icon' => 'banknotes',
        'tone' => 'green',
    ])
    @include('pharma::livewire.reporting.partials.filters')

    <section class="pa-section">
        <h2 class="pa-section__title">Récapitulatif</h2>
        <div class="pa-kpi-grid">
            @foreach ($cards as $card)
                @include('pharma::livewire.reporting.partials.kpi', ['card' => $card])
            @endforeach
        </div>
    </section>

    <div class="pa-grid pa-grid--2">
        <section class="pa-panel">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Modes de paiement</h2></div>
            <div class="pa-donut-row">
                <div class="pa-donut" style="background: {{ $paymentGradient }};">
                    <div class="pa-donut__hole"><strong>Paiements</strong></div>
                </div>
                <ul class="pa-legend">
                    @forelse ($payments as $slice)
                        <li>
                            <i style="background:{{ $slice['color'] }}"></i>
                            <span>{{ $slice['label'] }}</span>
                            <em>{{ fmt_num($slice['percent'], 1) }} %</em>
                            <strong>{{ fmt_money($slice['total']) }} {{ $currency }}</strong>
                        </li>
                    @empty
                        <li class="pa-muted">Aucun encaissement.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <section class="pa-panel">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Dépenses par nature</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead><tr><th>Catégorie</th><th class="is-num">Montant</th></tr></thead>
                    <tbody>
                        @forelse ($expenses as $row)
                            <tr>
                                <td>{{ $row['category'] }}</td>
                                <td class="is-num">{{ fmt_money($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="pa-empty">Aucune dépense.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="pa-muted" style="padding:12px 16px;">Créances ouvertes : {{ fmt_money($debts['receivables']) }} {{ $currency }} · retard : {{ $debts['overdue_count'] }} dossier(s).</p>
        </section>
    </div>
</div>
