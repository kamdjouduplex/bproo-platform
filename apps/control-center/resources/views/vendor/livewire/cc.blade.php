@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

@if ($paginator->hasPages())
    <nav class="cc-pagination" role="navigation" aria-label="Pagination">
        <p class="cc-pagination__summary">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            sur {{ $paginator->total() }}
        </p>
        <div class="cc-pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm" aria-disabled="true">←</span>
            @else
                <button type="button" class="btn btn-secondary btn-sm" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">←</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="cc-pagination__ellipsis">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary btn-sm" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" class="btn btn-secondary btn-sm" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">→</button>
            @else
                <span class="btn btn-secondary btn-sm" aria-disabled="true">→</span>
            @endif
        </div>
    </nav>
@endif
