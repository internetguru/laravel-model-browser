@php
    $prevContent = '<i class="fas fa-fw fa-chevron-left" title="' . __('model-browser::pagination.previous') . '"></i>';
    $nextContent = '<i class="fas fa-fw fa-chevron-right" title="' . __('model-browser::pagination.next') . '"></i>';

    $firstPage = $data->onFirstPage();
    $morePages = $data->hasMorePages();
    $currentPage = $data->currentPage();
    $itemStartNum = ($currentPage - 1) * $data->perPage() + 1;
    $itemEndNum = $data->count() < $data->perPage() ? $itemStartNum + $data->count() - 1 : $currentPage * $data->perPage();

    $showPerPage = $showPerPage ?? false;
    $perPageOptions = $perPageOptions ?? [20, 50, 100];
@endphp

<nav role="navigation" aria-label="Pagination Navigation" class="d-flex align-items-center justify-content-end gap-3 my-3">
    <div>
        @lang('model-browser::pagination.page', ['page' => $currentPage, 'start' => $itemStartNum, 'end' => $itemEndNum])
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

@if ($showPerPage)
    <div class="d-flex justify-content-end">
        <div class="d-flex align-items-center gap-1">
            <span>@lang('model-browser::pagination.show')</span>
            @foreach ($perPageOptions as $option)
                @if ($data->perPage() == $option)
                    <strong>{{ $option }}</strong>
                @else
                    <button
                        class="btn btn-link p-0"
                        wire:click="setPerPage({{ $option }})"
                    >{{ $option }}</button>
                @endif
                @if (! $loop->last)
                    <span>/</span>
                @endif
            @endforeach
            <span>@lang('model-browser::pagination.per-page')</span>
        </div>
    </div>
@endif
