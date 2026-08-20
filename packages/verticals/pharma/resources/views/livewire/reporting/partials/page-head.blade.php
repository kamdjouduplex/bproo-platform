@php
    $code = $code ?? null;
    $icon = $icon ?? 'document';
    $tone = $tone ?? 'teal';
    $excelUrl = $excelUrl ?? null;
    $pdfUrl = $pdfUrl ?? null;
@endphp
<header class="pa-hero pa-hero--list">
    <div>
        <div class="pa-hero__title-row">
            <x-ui-icon-box :tone="$tone" :icon="$icon" />
            <h1 class="pa-hero__title">{{ $title }}</h1>
        </div>
        <p class="pa-hero__lead">
            @if ($code)
                <span class="pa-hero__code">{{ $code }}</span>
            @endif
            {{ $lead }}
        </p>
    </div>
    <div class="pa-hero__actions">
        @if (!empty($canExport) && $excelUrl)
            <a class="pa-btn pa-btn--excel" href="{{ $excelUrl }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Exporter Excel
            </a>
        @endif
        @if (!empty($canExport) && $pdfUrl)
            <a class="pa-btn pa-btn--pdf" href="{{ $pdfUrl }}" onclick="window.open(this.href, '_blank'); return false;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Exporter PDF
            </a>
        @endif
        @isset($slot)
            {{ $slot }}
        @endisset
    </div>
</header>
