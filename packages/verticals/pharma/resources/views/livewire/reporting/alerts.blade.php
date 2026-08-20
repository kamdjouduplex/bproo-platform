<div class="pa-page" wire:loading.class="is-loading">
    @include('pharma::livewire.reporting.partials.nav')
    @include('pharma::livewire.reporting.partials.page-head', [
        'code' => 'RP0601',
        'title' => 'Alertes',
        'lead' => 'Ruptures, péremption, stock dormant et créances à recouvrer.',
        'icon' => 'alert',
        'tone' => 'rose',
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

    @if (count($alerts) > 0)
        <ul class="pa-alert-list pa-alert-list--wide">
            @foreach ($alerts as $alert)
                <li class="pa-alert pa-alert--{{ $alert['tone'] }}">
                    <x-ui-icon-box :tone="$alert['tone']" :icon="$alert['icon']" />
                    <div>
                        <strong>{{ $alert['title'] }}</strong>
                        <div>{{ $alert['value'] }}</div>
                        <span class="pa-muted">{{ $alert['hint'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="pa-grid pa-grid--2">
        <section class="pa-panel">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Ruptures</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead><tr><th>Produit</th><th class="is-num">Dispo</th></tr></thead>
                    <tbody>
                        @forelse ($stockouts as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="is-num">{{ fmt_num($row['available']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="pa-empty">Aucune rupture.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="pa-panel">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Lots &lt; 90 jours</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead><tr><th>Lot</th><th>Produit</th><th>Expiration</th><th class="is-num">Jours</th></tr></thead>
                    <tbody>
                        @forelse ($expiring as $row)
                            <tr>
                                <td>{{ $row['batch_number'] }}</td>
                                <td>{{ $row['item_name'] }}</td>
                                <td>{{ $row['expiry_date'] }}</td>
                                <td class="is-num">{{ $row['days'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pa-empty">Aucun lot à risque.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="pa-panel">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Stock bas</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead><tr><th>Produit</th><th class="is-num">Dispo</th><th class="is-num">Seuil</th></tr></thead>
                    <tbody>
                        @forelse ($low as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="is-num">{{ fmt_num($row['available']) }}</td>
                                <td class="is-num">{{ $row['reorder_point'] !== null ? fmt_num($row['reorder_point']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="pa-empty">Aucun article sous seuil.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="pa-panel">
            <div class="pa-panel__head"><h2 class="pa-panel__title">Stock dormant</h2></div>
            <div class="table-scroll">
                <table class="pa-table">
                    <thead><tr><th>Produit</th><th class="is-num">Dispo</th><th>Dernière vente</th></tr></thead>
                    <tbody>
                        @forelse ($dead as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="is-num">{{ fmt_num($row['available']) }}</td>
                                <td>{{ $row['last_sale'] ?: 'Jamais' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="pa-empty">Pas de stock dormant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
