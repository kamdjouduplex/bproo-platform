@php
    $presets = [
        'today' => 'Aujourd’hui',
        'last_7_days' => '7 derniers jours',
        'this_month' => 'Ce mois',
        'last_month' => 'Mois dernier',
        'this_year' => 'Cette année',
        'custom' => 'Personnalisée',
    ];
@endphp
<div class="pa-period">
    <label class="pa-period__label">Période</label>
    <select class="input input-sm pa-period__select" wire:model.live="period">
        @foreach ($presets as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    @if ($period === 'custom')
        <input type="date" class="input input-sm" wire:model="dateFrom">
        <span class="pa-period__sep">→</span>
        <input type="date" class="input input-sm" wire:model="dateTo">
        <button type="button" class="btn btn-primary btn-sm" wire:click="applyCustomPeriod">Appliquer</button>
    @endif
    <span class="pa-period__pill">{{ $periodLabel }}</span>
</div>
