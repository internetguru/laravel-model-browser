@php
    $prevContent = '<i class="fas fa-fw fa-chevron-left" title="' . __('model-browser::pagination.previous') . '"></i>';
    $nextContent = '<i class="fas fa-fw fa-chevron-right" title="' . __('model-browser::pagination.next') . '"></i>';

    $firstPage = $data->onFirstPage();
    $morePages = $data->hasMorePages();
    $currentPage = $data->currentPage();
    $itemCount = count($data->items());
@endphp

<nav role="navigation" aria-label="Pagination Navigation" class="d-flex align-items-center justify-content-end gap-3 my-3">
    <div>
        @lang('model-browser::pagination.page', ['page' => $currentPage, 'count' => $itemCount])
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
