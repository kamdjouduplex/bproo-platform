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
        <div class="app-pagination__links">
            @if ($paginator->onFirstPage())
                <span class="app-pagination__btn is-disabled" aria-disabled="true">Préc.</span>
            @else
                @if (method_exists($paginator, 'getCursorName'))
                    <button
                        type="button"
                        dusk="previousPage"
                        wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()->encode() }}"
                        wire:click="setPage('{{ $paginator->previousCursor()->encode() }}','{{ $paginator->getCursorName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="app-pagination__btn"
                    >Préc.</button>
                @else
                    <button
                        type="button"
                        dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="app-pagination__btn"
                    >Préc.</button>
                @endif
            @endif

            @if ($paginator->hasMorePages())
                @if (method_exists($paginator, 'getCursorName'))
                    <button
                        type="button"
                        dusk="nextPage"
                        wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()->encode() }}"
                        wire:click="setPage('{{ $paginator->nextCursor()->encode() }}','{{ $paginator->getCursorName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="app-pagination__btn"
                    >Suiv.</button>
                @else
                    <button
                        type="button"
                        dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="app-pagination__btn"
                    >Suiv.</button>
                @endif
            @else
                <span class="app-pagination__btn is-disabled" aria-disabled="true">Suiv.</span>
            @endif
        </div>
    </nav>
@endif
