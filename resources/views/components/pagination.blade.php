@php
    $prevContent = '<i class="fas fa-fw fa-chevron-left" title="' . __('model-browser::pagination.previous') . '"></i>';
    $nextContent = '<i class="fas fa-fw fa-chevron-right" title="' . __('model-browser::pagination.next') . '"></i>';

    $firstPage = $data->onFirstPage();
    $morePages = $data->hasMorePages();
    $currentPage = $data->currentPage();
    $itemStartNum = ($currentPage - 1) * $data->perPage() + 1;
    $itemEndNum = $data->count() < $data->perPage() ? $itemStartNum + $data->count() - 1 : $currentPage * $data->perPage();

    $perPageOptions = $perPageOptions ?? [20, 50, 100];
    $totalCount = $totalCount ?? null;
@endphp

<nav role="navigation" aria-label="Pagination Navigation" class="d-flex align-items-center justify-content-end gap-3 my-3">
    <div class="d-flex align-items-center gap-1 flex-wrap">
        {{ $itemStartNum }}–{{ $itemEndNum }}
        @lang('model-browser::pagination.of')
        @if ($totalCount === null)
            <span x-data x-init="$wire.loadTotalCount()">@lang('model-browser::pagination.many')</span>
        @else
            {{ $totalCount }}
        @endif
        <span class="text-muted mx-1">&nbsp;</span>
        @lang('model-browser::pagination.show')
        <select
            wire:change="setPerPage($event.target.value)"
            class="form-select per-page-select d-inline-block w-auto"
        >
            @foreach ($perPageOptions as $option)
                <option value="{{ $option }}" @selected($data->perPage() == $option)>{{ $option }}</option>
            @endforeach
        </select>
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
