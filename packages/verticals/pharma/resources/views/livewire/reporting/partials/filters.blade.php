<section class="pa-filters card">
    <div class="pa-filters__title">Filtres de recherche</div>
    <div class="pa-filters__grid pa-filters__grid--period">
        <label>Période
            <select class="input" wire:model.live="period">
                <option value="today">Aujourd’hui</option>
                <option value="last_7_days">7 derniers jours</option>
                <option value="this_month">Ce mois</option>
                <option value="last_month">Mois dernier</option>
                <option value="this_year">Cette année</option>
                <option value="custom">Personnalisée</option>
            </select>
        </label>
        @if ($period === 'custom')
            <label>Du
                <input type="date" class="input" wire:model="dateFrom">
            </label>
            <label>Au
                <input type="date" class="input" wire:model="dateTo">
            </label>
        @endif
    </div>
    <div class="pa-filters__footer">
        <span class="pa-muted">{{ $periodLabel }}</span>
        <div class="pa-filters__actions">
            <button type="button" class="btn btn-secondary" wire:click="resetPeriod">Réinitialiser</button>
            @if ($period === 'custom')
                <button type="button" class="btn btn-primary" wire:click="applySearch">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
            @endif
        </div>
    </div>
</section>
