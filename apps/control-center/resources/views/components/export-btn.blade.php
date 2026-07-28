@props([
    'format' => 'excel', // excel | pdf
])

@php
    $format = strtolower((string) $format) === 'pdf' ? 'pdf' : 'excel';
    $label = trim((string) $slot) !== ''
        ? (string) $slot
        : ($format === 'pdf' ? 'Exporter PDF' : 'Exporter Excel');
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->class(['btn', 'btn-export', 'btn-export--' . $format]) }}>
        <x-file-type-icon :format="$format" class="btn-export__glyph" />
        <span class="btn-export__label">{{ $label }}</span>
    </a>
@else
    <button type="button" {{ $attributes->class(['btn', 'btn-export', 'btn-export--' . $format]) }}>
        <x-file-type-icon :format="$format" class="btn-export__glyph" />
        <span class="btn-export__label">{{ $label }}</span>
    </button>
@endif
