@props([
    'format' => 'excel', // excel | pdf
])

@php
    $format = strtolower((string) $format) === 'pdf' ? 'pdf' : 'excel';
@endphp

@if ($format === 'pdf')
    {{-- Icône document PDF (rouge professionnel) --}}
    <svg {{ $attributes->class(['file-type-icon', 'file-type-icon--pdf']) }} viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none">
        <path d="M7 3.75h6.5L19 9.25V20.25a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" fill="currentColor" opacity="0.14"/>
        <path d="M13.5 3.75V8.5a.75.75 0 0 0 .75.75H19" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M7 3.75h6.5L19 9.25V20.25a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M8.4 16.1V12.4h1.55c.95 0 1.55.52 1.55 1.32 0 .82-.62 1.34-1.58 1.34H9.55v1.04H8.4Zm1.15-1.85h.38c.38 0 .62-.2.62-.52s-.24-.5-.62-.5h-.38v1.02Zm3.05 1.85V12.4h1.1c1.28 0 2.08.78 2.08 1.85s-.8 1.85-2.08 1.85h-1.1Zm1.15-2.72v1.78h.22c.55 0 .9-.35.9-.89 0-.53-.35-.89-.9-.89h-.22Zm3.2 2.72h-1.05l1.22-3.7h1.18l1.22 3.7h-1.08l-.22-.72h-1.05l-.22.72Zm.74-1.55.34-1.12h.02l.34 1.12h-.7Z" fill="currentColor"/>
    </svg>
@else
    {{-- Icône tableur Excel (vert professionnel) --}}
    <svg {{ $attributes->class(['file-type-icon', 'file-type-icon--excel']) }} viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none">
        <path d="M5.5 4.5h9.2L19 8.8V19.5a1 1 0 0 1-1 1H5.5a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1Z" fill="currentColor" opacity="0.14"/>
        <path d="M14.7 4.5V8.3a.7.7 0 0 0 .7.7H19" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M5.5 4.5h9.2L19 8.8V19.5a1 1 0 0 1-1 1H5.5a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M8.2 17.2 10.55 13.8 8.35 10.5h1.35l1.42 2.2h.04l1.45-2.2h1.3L11.7 13.8l2.35 3.4h-1.38l-1.55-2.35h-.04l-1.55 2.35H8.2Z" fill="currentColor"/>
    </svg>
@endif
