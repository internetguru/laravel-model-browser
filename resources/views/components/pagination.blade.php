{{--
    The result window and the total count on one line: `1–20 of 176 [<] [>]`.

    The total is passed in as the `count` slot rather than rendered here: it lives in
    the component's "count" island, so it loads and refreshes on its own without
    re-running the (potentially expensive) data query.
--}}
@php
    $prevContent = '<i class="fas fa-fw fa-chevron-left" title="' . __('model-browser::pagination.previous') . '"></i>';
    $nextContent = '<i class="fas fa-fw fa-chevron-right" title="' . __('model-browser::pagination.next') . '"></i>';

    $firstPage = $skip === 0;
    $morePages = $data->hasMorePages();
    // The page can be taller than one page's worth of rows once "load more" has been
    // used, so the window is counted off the offset rather than off the page size.
    $shown = $data->count();
    $itemStartNum = $shown ? $skip + 1 : 0;
    $itemEndNum = $shown ? $skip + $shown : 0;
@endphp

<nav role="navigation" aria-label="Pagination Navigation" class="model-browser__pagination d-flex align-items-center justify-content-end gap-3 my-3">
    <div class="d-flex align-items-center gap-1 flex-wrap">
        <span class="model-browser__pagination-range">{{ $itemStartNum }}–{{ $itemEndNum }}</span>
        <span>@lang('model-browser::pagination.of')</span>
        {{ $count }}
    </div>
    <div>
        @if ($firstPage)
            <button class="btn btn-light btn-sm" disabled>{!! $prevContent !!}</button>
        @else
            <button class="btn btn-light btn-sm" wire:click="previousPage" wire:loading.attr="disabled" rel="prev">{!! $prevContent !!}</button>
        @endif

        @if ($morePages)
            <button class="btn btn-light btn-sm" wire:click="nextPage" wire:loading.attr="disabled" rel="next">{!! $nextContent !!}</button>
        @else
            <button class="btn btn-light btn-sm" disabled>{!! $nextContent !!}</button>
        @endif
    </div>
</nav>
