@php
    $prevContent = '<i class="fas fa-fw fa-chevron-left me-1"></i>' . __('model-browser::pagination.previous');
    $nextContent = __('model-browser::pagination.next') . '<i class="fas fa-fw fa-chevron-right ms-1"></i>';
@endphp

<nav role="navigation" aria-label="Pagination Navigation" class="d-flex flex-wrap justify-content-center align-items-center gap-3">
    <span>
        @if ($data->onFirstPage())
            <button class="btn btn-light btn-sm" disabled>{!! $prevContent !!}</button>
        @else
            <button class="btn btn-light btn-sm" wire:click="previousPage" wire:loading.attr="disabled" rel="prev">{!! $prevContent !!}</button>
        @endif
    </span>

    <span>
        @lang('model-browser::pagination.page', ['page' => $data->currentPage(), 'lastPage' => $data->lastPage()])
    </span>

    <select name="perPage" wire:model.live="perPage" class="form-select form-select-sm" style="width: 5em;">
        <option value="3">3</option>
        <option value="9">9</option>
        <option value="15">15</option>
        <option value="30">30</option>
        <option value="60">60</option>
        <option value="120">120</option>
    </select>

    <span>
        @if ($data->onLastPage())
            <button class="btn btn-light btn-sm" disabled>{!! $nextContent !!}</button>
        @else
            <button class="btn btn-light btn-sm" wire:click="nextPage" wire:loading.attr="disabled" rel="next">{!! $nextContent !!}</button>
        @endif
    </span>
</nav>
