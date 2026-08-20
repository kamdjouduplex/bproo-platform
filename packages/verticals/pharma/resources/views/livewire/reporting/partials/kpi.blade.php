@php
    $trend = $card['trend'] ?? null;
    $invert = $card['invert_trend'] ?? false;
    $pts = $card['pts'] ?? false;
    $up = $trend !== null && $trend >= 0;
    $good = $trend === null ? null : ($invert ? ! $up : $up);
@endphp
<article class="pa-kpi pa-kpi--{{ $card['tone'] ?? 'teal' }}">
    <div class="pa-kpi__top">
        <x-ui-icon-box :tone="$card['tone'] ?? 'teal'" :icon="$card['icon'] ?? 'chart'" />
        <span class="pa-kpi__label">{{ $card['label'] }}</span>
    </div>
    <div class="pa-kpi__value">{{ $card['value'] }}</div>
    @if ($trend !== null)
        <div class="pa-kpi__trend {{ $good ? 'is-up' : 'is-down' }}">
            {{ $trend >= 0 ? '+' : '' }}{{ fmt_num($trend, 1) }}{{ $pts ? ' pts' : ' %' }}
            <span>vs période préc.</span>
        </div>
    @endif
</article>
