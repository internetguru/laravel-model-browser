<div wire:lazy class="model-browser model-browser-table">
    <div
        wire:ignore.self
        class="table-wrapper"
        x-on:fullscreen="
            const fullscreen = $event.detail.fullscreen
            this.fullscreen = fullscreen
            $el.classList.toggle('fullscreen--active', fullscreen)
        "
        x-data="{
            sortColumn: function(column) {
                let currentColumn = $wire.sortColumn;
                let currentDirection = $wire.sortDirection;
                if (currentColumn === column) {
                    if (currentDirection === 'asc') {
                        $wire.set('sortDirection', 'desc');
                    } else {
                        $wire.set('sortColumn', '');
                        $wire.set('sortDirection', 'asc');
                    }
                } else {
                    $wire.set('sortColumn', column);
                    $wire.set('sortDirection', 'asc');
                }
            }
        }"
    >

        <div class="d-flex justify-content-end alig-items-center gap-3 m-3">
            <div class="mt-3">
                <x-model-browser::fullscreen-button />
            </div>
        </div>

        <x-model-browser::filters :$filterConfig :$filterValues :$searchQuery />

        <div>
            <x-model-browser::pagination :$data :$perPageOptions :$totalCount />
        </div>

        <div
            @if ($refreshInterval) wire:poll.{{ $refreshInterval }}s @endif
            class="table-responsive"
        >
            <div class="grid-table" style="grid-template-columns: {{ $this->generateGridColumns() }};">
                <div class="grid-header">
                    @foreach($viewAttributes as $column => $trans)
                        <div class="grid-header-cell">
                            <span class="d-flex align-items-center gap-1">
                                @php
                                    $activeSortColumn = $this->getActiveSortColumn();
                                    $activeSortDirection = $this->getActiveSortDirection();
                                    $isCurrentSortColumn = $activeSortColumn === $column;
                                @endphp
                                @if ($enableSort)
                                    <span x-on:click="sortColumn('{{ $column }}')" style="cursor: pointer;">
                                        @if ($isCurrentSortColumn)
                                            <i @class([
                                                "fas fa-fw",
                                                "fa-up-long" => $activeSortDirection === 'asc',
                                                "fa-down-long" => $activeSortDirection === 'desc',
                                            ])></i>
                                        @else
                                            <i class="fas fa-fw fa-up-down"></i>
                                        @endif
                                    </span>
                                @elseif ($isCurrentSortColumn)
                                    <i @class([
                                        "fas fa-fw",
                                        "fa-up-long" => $activeSortDirection === 'asc',
                                        "fa-down-long" => $activeSortDirection === 'desc',
                                    ])></i>
                                @endif
                                {{ $trans }}
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($data->isNotEmpty())
                    @foreach($data as $row)
                        <div @class([
                            'grid-row',
                            'grid-row-light' => ($loop->index / $lightDarkStep) % 2 == 1,
                        ])>
                            @foreach($viewAttributes as $column => $trans)
                                <div
                                    @class([
                                        'grid-cell',
                                        'text-' . $this->getAlignment($column, Arr::get($row, $column)),
                                    ])
                                ><span>{!!
                                    $this->itemValue($row, $column)
                                !!}</span></div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    <div class="grid-no-results">
                        <div>@lang('model-browser::global.no-results')</div>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <x-model-browser::pagination :$data :$perPageOptions :$totalCount />
        </div>

        <x-model-browser::csv-buttons />

    </div>
</div>
