@php
    $prevContent = '<i class="fas fa-fw fa-chevron-left" title="' . __('model-browser::pagination.previous') . '"></i>';
    $nextContent = '<i class="fas fa-fw fa-chevron-right" title="' . __('model-browser::pagination.next') . '"></i>';

    if ($data->isNotEmpty()) {
        $from = ($data->currentPage() - 1) * $data->perPage() + 1;
        $to = min($data->currentPage() * $data->perPage(), $data->total());
        $total = $data->total();
        $fistPage = $data->onFirstPage();
        $morePages = $data->hasMorePages();
    } else {
        $from = 0;
        $to = 0;
        $total = 0;
        $fistPage = false;
        $morePages = false;
    }
@endphp

<nav role="navigation" aria-label="Pagination Navigation" class="d-flex align-items-center justify-content-end gap-3">
    <div>
        @lang('model-browser::pagination.range', ['from' => $from, 'to' => $to, 'total' => $total])
    </div>
    <div class="me-3">
        @if ($fistPage)
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
