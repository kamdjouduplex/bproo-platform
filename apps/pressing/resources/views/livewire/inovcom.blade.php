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
    <nav class="app-pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        @if ($paginator->total() > 0)
            <p class="app-pagination__info">
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
                sur {{ $paginator->total() }}
            </p>
        @endif

        <div class="app-pagination__links">
            @if ($paginator->onFirstPage())
                <span class="app-pagination__btn is-disabled" aria-disabled="true">Préc.</span>
            @else
                <button
                    type="button"
                    dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    class="app-pagination__btn"
                >Préc.</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="app-pagination__ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="app-pagination__btn is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <button
                                type="button"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled"
                                x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                class="app-pagination__btn"
                            >{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    class="app-pagination__btn"
                >Suiv.</button>
            @else
                <span class="app-pagination__btn is-disabled" aria-disabled="true">Suiv.</span>
            @endif
        </div>
    </nav>
@endif
