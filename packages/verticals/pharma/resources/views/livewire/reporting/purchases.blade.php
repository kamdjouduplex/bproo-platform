<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')
    @include('pharma::livewire.reporting.partials.page-head', [
        'code' => 'RP0401',
        'title' => 'Achats & fournisseurs',
        'lead' => 'Commandes, réceptions et concentration fournisseur.',
        'icon' => 'package',
        'tone' => 'amber',
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
            <div class="pa-panel__head"><h2 class="pa-panel__title">Top fournisseurs</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead><tr><th>Fournisseur</th><th class="is-num">Commandes</th><th class="is-num">Montant</th></tr></thead>
                    <tbody>
                        @forelse ($byProvider as $row)
                            <tr>
                                <td>{{ $row['provider'] }}</td>
                                <td class="is-num">{{ $row['orders'] }}</td>
                                <td class="is-num">{{ fmt_money($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="pa-empty">Aucun achat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pa-panel pa-panel--table">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Commandes de la période</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Fournisseur</th>
                            <th>Statut</th>
                            <th class="is-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $row)
                            <tr>
                                <td>
                                    @if (\Illuminate\Support\Facades\Route::has('tenant.purchases.show'))
                                        <a href="{{ route('tenant.purchases.show', [$row['id'], 'tenant' => $tenantCode]) }}">{{ $row['order_number'] }}</a>
                                    @else
                                        {{ $row['order_number'] }}
                                    @endif
                                </td>
                                <td>{{ $row['order_date'] }}</td>
                                <td>{{ $row['provider'] }}</td>
                                <td><span class="pa-badge pa-badge--{{ $row['status'] === 'received' ? 'good' : ($row['status'] === 'cancelled' ? 'bad' : 'warn') }}">{{ $row['status_label'] }}</span></td>
                                <td class="is-num">{{ fmt_money($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pa-empty">Aucune commande.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
