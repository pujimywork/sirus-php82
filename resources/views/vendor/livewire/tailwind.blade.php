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

{{-- Pagination ber-tema brand — gaya .ds-page-btn (sama dengan /panduan-dev):
     tombol terpisah rounded, halaman aktif hijau brand solid. --}}
<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation"
            class="flex flex-wrap items-center justify-between gap-3">

            {{-- Info jumlah --}}
            <p class="text-sm text-muted dark:text-gray-400">
                Menampilkan
                <span class="font-medium text-ink dark:text-gray-100">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-ink dark:text-gray-100">{{ $paginator->lastItem() }}</span>
                dari <span class="font-medium text-ink dark:text-gray-100">{{ $paginator->total() }}</span> data
            </p>

            {{-- Tombol halaman --}}
            <div class="flex items-center gap-1.5">
                {{-- Previous --}}
                @if ($paginator->onFirstPage())
                    <span class="ds-page-btn" aria-disabled="true" style="opacity:.45;cursor:not-allowed" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    </span>
                @else
                    <button type="button" class="ds-page-btn" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled"
                        dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" aria-label="{{ __('pagination.previous') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                @endif

                {{-- Page numbers (sembunyikan di layar sangat sempit, sisakan prev/next) --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="ds-page-btn hidden sm:inline-flex" style="cursor:default" aria-disabled="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="ds-page-btn ds-page-btn-active hidden sm:inline-flex" aria-current="page" wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">{{ $page }}</span>
                            @else
                                <button type="button" class="ds-page-btn hidden sm:inline-flex" wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next --}}
                @if ($paginator->hasMorePages())
                    <button type="button" class="ds-page-btn" wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled"
                        dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" aria-label="{{ __('pagination.next') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </button>
                @else
                    <span class="ds-page-btn" aria-disabled="true" style="opacity:.45;cursor:not-allowed" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
