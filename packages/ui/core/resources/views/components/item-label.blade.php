@props([
    'reference' => null,
    'name' => null,
    'fallback' => '—',
])

@php
    $ref = item_primary_label($reference, $name, '');
    $sub = item_secondary_label($reference, $name);

    if ($ref === '' && $sub === null) {
        $ref = $fallback;
        $sub = null;
    }
@endphp

<span {{ $attributes->class(['item-label']) }}>
    <span class="item-label__ref">{{ $ref }}</span>
    @if ($sub)
        <span class="item-label__name">{{ $sub }}</span>
    @endif
</span>

