@php
    $filters = $catalog['filters'] ?? [];
    $statuses = $catalog['statuses'] ?? [];
    $headers = $result['headers'] ?? [];
    $rows = $result['rows'] ?? [];
    $totals = $result['totals'] ?? [];
    $paginator = $result['paginator'];
    $visibleHeaders = array_values(array_filter($headers, fn ($col) => ! in_array($col['key'], $hiddenColumns, true)));
    $badgeClass = function (string $status): string {
        return match ($status) {
            'paid', 'active', 'received', 'approved', 'ok', 'accepted' => 'rpt-badge--paid',
            'issued', 'cancelled', 'rejected', 'out' => 'rpt-badge--unpaid',
            'partial', 'pending', 'sent', 'low' => 'rpt-badge--wait',
            default => 'rpt-badge--info',
        };
    };
@endphp

<div class="rpt" wire:loading.class="rpt--loading">
    <header class="rpt-head">
        <div>
            <h1 class="rpt-head__title">Rapports et analyses</h1>
            <p class="rpt-head__sub">Générez, filtrez et exportez vos données selon vos besoins.</p>
        </div>
        <button type="button" class="btn btn-secondary" wire:click="resetFilters">
            Réinitialiser les filtres
        </button>
    </header>

    <section class="rpt-filters">
        <div class="rpt-filters__head">
            <strong>Filtres</strong>
            <span>{{ $periodLabel }}</span>
        </div>
        <div class="rpt-filters__grid">
            <label class="rpt-field">
                <span>Module</span>
                <select class="rpt-select" wire:model.live="module">
                    @foreach ($modules as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="rpt-field">
                <span>Rapport</span>
                <select class="rpt-select" wire:model="report">
                    @foreach ($modules[$module]['reports'] ?? [] as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="rpt-field">
                <span>Période</span>
                <select class="rpt-select" wire:model.live="period">
                    <option value="daily">Aujourd’hui</option>
                    <option value="weekly">Cette semaine</option>
                    <option value="monthly">Ce mois</option>
                    <option value="yearly">Cette année</option>
                    <option value="custom">Personnalisée</option>
                </select>
            </label>
            <label class="rpt-field">
                <span>Du</span>
                <input class="rpt-input" type="date" wire:model="dateFrom">
            </label>
            <label class="rpt-field">
                <span>Au</span>
                <input class="rpt-input" type="date" wire:model="dateTo">
            </label>

            @if (in_array('store', $filters, true) && count($stores) > 0)
                <label class="rpt-field">
                    <span>Entité / Point de vente</span>
                    <select class="rpt-select" wire:model="storeId">
                        <option value="">Tous</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store['id'] }}">{{ $store['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if (in_array('client', $filters, true))
                <div class="rpt-field">
                    <span>Client</span>
                    @if ($clientId !== '')
                        <div class="rpt-chip">
                            <span>{{ $clientSearch !== '' ? $clientSearch : 'Client #'.$clientId }}</span>
                            <button type="button" wire:click="clearClient" aria-label="Retirer">×</button>
                        </div>
                    @else
                        <input class="rpt-input" type="search" wire:model.live.debounce.400ms="clientSearch" placeholder="Rechercher un client…">
                        @if (count($clientSuggestions) > 0)
                            <ul class="rpt-suggest">
                                @foreach ($clientSuggestions as $client)
                                    <li>
                                        <button type="button" wire:click="selectClient({{ $client['id'] }}, @js($client['name']))">{{ $client['name'] }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            @endif

            @if (in_array('status', $filters, true) && count($statuses) > 0)
                <label class="rpt-field">
                    <span>Statut</span>
                    <select class="rpt-select" wire:model="status">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if (in_array('category', $filters, true))
                <label class="rpt-field">
                    <span>Catégorie</span>
                    <select class="rpt-select" wire:model="categoryId">
                        <option value="">Tous</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if (in_array('user', $filters, true))
                <label class="rpt-field">
                    <span>Commercial</span>
                    <select class="rpt-select" wire:model="userId">
                        <option value="">Tous</option>
                        @foreach ($users as $user)
                            <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if (in_array('item', $filters, true))
                <div class="rpt-field">
                    <span>Produit / Article</span>
                    @if ($itemId !== '')
                        <div class="rpt-chip">
                            <span>{{ $itemSearch !== '' ? $itemSearch : 'Article #'.$itemId }}</span>
                            <button type="button" wire:click="clearItem" aria-label="Retirer">×</button>
                        </div>
                    @else
                        <input class="rpt-input" type="search" wire:model.live.debounce.400ms="itemSearch" placeholder="Sélectionner un article…">
                        @if (count($itemSuggestions) > 0)
                            <ul class="rpt-suggest">
                                @foreach ($itemSuggestions as $item)
                                    <li>
                                        <button type="button" wire:click="selectItem({{ $item['id'] }}, @js($item['name']))">{{ $item['name'] }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        @if ($moreFilters)
            <div class="rpt-filters__grid">
                @if (in_array('amount', $filters, true))
                    <label class="rpt-field">
                        <span>Montant min</span>
                        <input class="rpt-input" type="number" min="0" step="1" wire:model="amountMin" placeholder="Min">
                    </label>
                    <label class="rpt-field">
                        <span>Montant max</span>
                        <input class="rpt-input" type="number" min="0" step="1" wire:model="amountMax" placeholder="Max">
                    </label>
                @endif
            </div>
        @endif

        <div class="rpt-filters__foot">
            @if (in_array('amount', $filters, true))
                <button type="button" class="rpt-link" wire:click="$toggle('moreFilters')">
                    {{ $moreFilters ? 'Moins de filtres' : 'Plus de filtres' }}
                </button>
            @else
                <span></span>
            @endif
            <button type="button" class="btn btn-primary" wire:click="applyFilters">Appliquer les filtres</button>
        </div>
    </section>

    <section class="rpt-results">
        <div class="rpt-results__bar">
            <div class="rpt-results__title">
                <div>
                    <h2>Résultats du rapport</h2>
                    @if (! empty($catalog['hint']))
                        <p class="rpt-hint">{{ $catalog['hint'] }}</p>
                    @endif
                </div>
                <span class="rpt-count">{{ fmt_num($paginator->total()) }} lignes</span>
            </div>
            <div class="rpt-results__actions">
                <details class="rpt-cols">
                    <summary class="btn btn-secondary btn-sm">Colonnes</summary>
                    <div class="rpt-cols__menu">
                        @foreach ($headers as $col)
                            <label>
                                <input type="checkbox" @checked(! in_array($col['key'], $hiddenColumns, true)) wire:click="toggleColumn('{{ $col['key'] }}')">
                                {{ $col['label'] }}
                            </label>
                        @endforeach
                    </div>
                </details>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="applyFilters">Actualiser</button>
                @if ($canExport)
                    <button type="button" class="btn btn-primary btn-sm" wire:click="exportExcel">Exporter Excel</button>
                    <a class="btn btn-sm rpt-btn-pdf" href="{{ $this->exportPdfUrl() }}" target="_blank" rel="noopener">Exporter PDF</a>
                @endif
            </div>
        </div>

        @if (count($rows) === 0)
            <p class="rpt-empty">Aucune ligne pour ces filtres. Modifiez la période ou cliquez sur « Appliquer les filtres ».</p>
        @else
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            @foreach ($visibleHeaders as $col)
                                <th class="{{ ($col['type'] ?? '') === 'money' ? 'rpt-num' : '' }}">
                                    <button type="button" class="rpt-sort" wire:click="sortBy('{{ $col['key'] }}')">
                                        {{ $col['label'] }}
                                        @if ($sort === $col['key'])
                                            <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($visibleHeaders as $col)
                                    @php $key = $col['key']; $type = $row['type'] ?? ($col['type'] ?? 'text'); @endphp
                                    <td class="{{ in_array($type, ['money', 'int', 'qty', 'percent'], true) ? 'rpt-num' : '' }}">
                                        @if ($type === 'badge')
                                            <span class="rpt-badge {{ $badgeClass((string) ($row['status'] ?? '')) }}">{{ $row['status_label'] ?? $row[$key] }}</span>
                                        @elseif ($type === 'money')
                                            {{ ($row[$key] ?? null) === null ? '—' : fmt_money($row[$key]) }}
                                        @elseif ($type === 'qty')
                                            {{ fmt_num($row[$key] ?? 0) }}
                                        @elseif ($type === 'int')
                                            {{ ($row[$key] ?? null) === null ? '—' : fmt_num($row[$key], 0) }}
                                        @elseif ($type === 'percent')
                                            {{ ($row[$key] ?? null) === null ? '—' : number_format((float) $row[$key], 1, ',', ' ').' %' }}
                                        @else
                                            {{ $row[$key] ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (count($totals) > 0)
                        <tfoot>
                            <tr>
                                @foreach ($visibleHeaders as $i => $col)
                                    <td class="{{ in_array($col['type'] ?? '', ['money', 'int', 'qty', 'percent'], true) ? 'rpt-num' : '' }}">
                                        @if ($i === 0)
                                            Total
                                        @elseif (array_key_exists($col['key'], $totals) && $totals[$col['key']] !== null)
                                            @if (($col['type'] ?? '') === 'money')
                                                {{ fmt_money($totals[$col['key']]) }} {{ $currency }}
                                            @elseif (($col['type'] ?? '') === 'percent')
                                                {{ number_format((float) $totals[$col['key']], 1, ',', ' ') }} %
                                            @else
                                                {{ fmt_num($totals[$col['key']]) }}
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div class="rpt-pager">
                <span>Affichage {{ $paginator->firstItem() ?? 0 }} à {{ $paginator->lastItem() ?? 0 }} sur {{ $paginator->total() }} lignes</span>
                @php
                    $last = max(1, $paginator->lastPage());
                    $current = $paginator->currentPage();
                    $startPage = max(1, $current - 2);
                    $endPage = min($last, $startPage + 4);
                    $startPage = max(1, $endPage - 4);
                @endphp
                <div class="rpt-pages">
                    <button type="button" class="rpt-page" wire:click="previousPage" @disabled($paginator->onFirstPage())>‹</button>
                    @for ($p = $startPage; $p <= $endPage; $p++)
                        <button type="button" class="rpt-page {{ $current === $p ? 'is-active' : '' }}" wire:click="gotoPage({{ $p }})">{{ $p }}</button>
                    @endfor
                    <button type="button" class="rpt-page" wire:click="nextPage" @disabled($paginator->onLastPage())>›</button>
                </div>
                <select class="rpt-select rpt-select--sm" wire:model.live="perPage">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
        @endif
    </section>
</div>
