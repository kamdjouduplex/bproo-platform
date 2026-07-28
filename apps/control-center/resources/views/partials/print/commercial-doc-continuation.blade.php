<div class="doc-continuation">
    <div>
        <strong>{{ $docLabel }}</strong> {{ $docNumber }}
        @if (!empty($clientName))
            <span class="doc-continuation__sep">—</span> {{ $clientName }}
        @endif
    </div>
    <div class="doc-continuation__page">Page {{ ($pageIndex ?? 0) + 1 }} / {{ $totalPages ?? 1 }}</div>
</div>
