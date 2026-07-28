@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $summary = $summary ?? ['count' => 0, 'total_in' => 0, 'total_out' => 0, 'net' => 0];
@endphp

<div class="page-body stock-movements-page">
    <section class="card app-table-card stock-movements-page__card">
        <header class="stock-movements-page__header">
            <div class="stock-movements-page__top">
                <div class="stock-movements-page__intro">
                    @if ($item)
                        <p class="stock-movements-page__eyebrow">Fiche mouvements article</p>
                        <h2 class="stock-movements-page__title">
                            @if ($item->sku)<span class="stock-movements-page__sku">{{ $item->sku }}</span>@endif
                            {{ $item->name }}
                        </h2>
                        <p class="stock-movements-page__hint">
                            Chaque ligne explique clairement si le stock est entré ou sorti, de combien, et pourquoi.
                        </p>
                    @else
                        <p class="stock-movements-page__eyebrow">Journal stock</p>
                        <h2 class="stock-movements-page__title">Mouvements de stock</h2>
                        <p class="stock-movements-page__hint">
                            Filtrez un produit ou une période, puis imprimez le journal pour le consulter hors écran.
                        </p>
                    @endif
                </div>

                <div class="stock-movements-page__actions">
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.index', ['tenant' => $tenantCode]) }}">Retour stock</a>
                    @if ($item)
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.stock.movements', ['tenant' => $tenantCode]) }}">Tous les mouvements</a>
                        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.show', [$item->id, 'tenant' => $tenantCode]) }}">Fiche article</a>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="exportExcel">Excel</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="printPdf">Imprimer PDF</button>
                </div>
            </div>

            <div class="stock-movements-page__summary" aria-label="Résumé des mouvements filtrés">
                <div class="stock-movements-page__stat">
                    <span class="stock-movements-page__stat-label">Mouvements</span>
                    <strong class="stock-movements-page__stat-value">{{ number_format((int) $summary['count'], 0, ',', ' ') }}</strong>
                </div>
                <div class="stock-movements-page__stat stock-movements-page__stat--in">
                    <span class="stock-movements-page__stat-label">Entrées</span>
                    <strong class="stock-movements-page__stat-value">+{{ fmt_num((float) $summary['total_in']) }}</strong>
                </div>
                <div class="stock-movements-page__stat stock-movements-page__stat--out">
                    <span class="stock-movements-page__stat-label">Sorties</span>
                    <strong class="stock-movements-page__stat-value">−{{ fmt_num((float) $summary['total_out']) }}</strong>
                </div>
                @if ($item && array_key_exists('current_available', $summary) && $summary['current_available'] !== null)
                    <div class="stock-movements-page__stat">
                        <span class="stock-movements-page__stat-label">Stock dispo actuel</span>
                        <strong class="stock-movements-page__stat-value">{{ fmt_num((float) $summary['current_available']) }}</strong>
                    </div>
                @endif
            </div>

            <form class="stock-movements-page__filters" wire:submit.prevent="applyFilters">
                <div class="stock-movements-page__filters-row">
                    @if (!$item)
                        <div class="stock-movements-page__field stock-movements-page__field--search">
                            <label class="stock-movements-page__field-label" for="mv-search">Article</label>
                            <input id="mv-search" class="input input-sm" type="search"
                                   wire:model.live.debounce.250ms="search"
                                   placeholder="{{ item_search_placeholder() }}"
                                   autocomplete="off">
                        </div>
                    @endif
                    <div class="stock-movements-page__field">
                        <label class="stock-movements-page__field-label" for="mv-direction">Sens</label>
                        <select id="mv-direction" class="input input-sm" wire:model.live="direction">
                            <option value="">Tous les sens</option>
                            <option value="in">Entrées physiques</option>
                            <option value="out">Sorties physiques</option>
                            <option value="reserve">Réservations / libérations</option>
                        </select>
                    </div>
                    <div class="stock-movements-page__field">
                        <label class="stock-movements-page__field-label" for="mv-origin">Cause / origine</label>
                        <select id="mv-origin" class="input input-sm" wire:model.live="referenceType" @if($referenceId) disabled @endif>
                            @foreach ($referenceTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="stock-movements-page__field">
                        <label class="stock-movements-page__field-label" for="mv-from">Du</label>
                        <input id="mv-from" class="input input-sm" type="date" wire:model.live="dateFrom">
                    </div>
                    <div class="stock-movements-page__field">
                        <label class="stock-movements-page__field-label" for="mv-to">Au</label>
                        <input id="mv-to" class="input input-sm" type="date" wire:model.live="dateTo">
                    </div>
                    <div class="stock-movements-page__field stock-movements-page__field--actions">
                        <span class="stock-movements-page__field-label stock-movements-page__field-label--hidden">Actions</span>
                        <div class="stock-movements-page__filter-btns">
                            <button type="submit" class="btn btn-secondary btn-sm">Filtrer</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilters">Réinitialiser</button>
                        </div>
                    </div>
                </div>
                <p class="stock-movements-page__export-hint">Excel et PDF reprennent exactement les filtres affichés ci-dessus.</p>
            </form>
        </header>

        @include('inovcom-stock::partials.movements-table', [
            'rows' => $movements,
            'showItem' => !$item,
            'paginated' => true,
            'tenantCode' => $tenantCode,
        ])
    </section>
</div>
