<div class="model-browser model-browser-table">
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

        <div class="d-flex justify-content-end align-items-center gap-3 mx-3 mt-3 mb-2">
            <x-model-browser::fullscreen-button />
        </div>

        <x-model-browser::filters :$filterConfig :$filterValues :$searchQuery />

        <x-model-browser::pagination :data="$this->rows" :$skip>
            <x-slot:count>
                @island(name: 'count')
                    @include('model-browser::partials.count')
                @endisland
            </x-slot:count>
        </x-model-browser::pagination>

        <div
            @if ($refreshInterval) wire:poll.{{ $refreshInterval }}s @endif
            class="table-responsive mb-4"
        >
            <div class="grid-table" style="grid-template-columns: {{ $this->generateGridColumns() }};">
                <div class="grid-header">
                    {{--
                        The header sits in the "stats" island: the per-column statistics
                        load on their own and only these cells change when they arrive —
                        the (potentially expensive) data query below never re-runs.

                        Loading is what the island is for: `loadTotalStats` is called
                        scoped to it, so the component itself does not render. It is
                        `always` rendered otherwise — the cells carry the sort state
                        too, which has to follow every ordinary re-render.
                    --}}
                    @island(name: 'stats', always: true)
                        @if ($statsAttributes)
                            <span
                                style="display: none;"
                                x-data
                                @if ($stats === null && ! $statsOverLimit)
                                    x-init="$wire.$island('stats').loadTotalStats()"
                                @endif
                                x-on:mb-refresh-stats.window="$wire.$island('stats').loadTotalStats()"
                            ></span>
                        @endif
                        @foreach($viewAttributes as $column => $trans)
                            @php
                                $hasStats = in_array($column, $statsAttributes, true);
                                $columnStats = $this->columnStats($column);
                            @endphp
                            <div class="grid-header-cell @if ($hasStats) grid-header-cell--stats @endif">
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
                                    @endif
                                    {{ $trans }}
                                    @if ($hasStats)
                                        {{--
                                            How many rows have a value at all, worth saying only
                                            when some of them do not. The slot is rendered either
                                            way and keeps its width, so a summarized column does
                                            not jump when the count arrives or turns out to be moot.
                                        --}}
                                        <span
                                            class="model-browser__stats-filled"
                                            @if ($this->showsCountOfFilledRows($column))
                                                title="@lang('model-browser::global.stats.filled', ['count' => $columnStats['countnz'], 'total' => $columnStats['count']])"
                                            @endif
                                        >@if ($columnStats === null && ! $statsOverLimit)<span class="model-browser__stats-icon"><i class="fa-solid fa-spinner fa-spin"></i></span>@elseif ($this->showsCountOfFilledRows($column))({{ $columnStats['countnz'] }})@endif</span>
                                        <x-model-browser::column-stats
                                            :label="$trans"
                                            :rows="$this->columnStatsRows($column)"
                                            :loaded="$columnStats !== null"
                                            :over-limit="$statsOverLimit"
                                            :limit="$statsLimit"
                                        />
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    @endisland
                </div>

                @if ($this->rows->isNotEmpty())
                    @foreach($this->rows as $row)
                        <div @class([
                            'grid-row',
                            'grid-row-light' => ($loop->index / $lightDarkStep) % 2 == 1,
                        ])>
                            @foreach($viewAttributes as $column => $trans)
                                @php $rawValue = $this->itemValueRaw($row, $column); @endphp
                                <div
                                    @class([
                                        'grid-cell',
                                        'text-' . $this->getAlignment($column, Arr::get($row, $column)),
                                    ])
                                    @if ($rawValue !== null) data-raw="{{ $rawValue }}" @endif
                                ><span>{!!
                                    $this->itemValue($row, $column)
                                !!}</span></div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    <div class="grid-no-results">
                        @php
                            $hasActiveFilters = filled($searchQuery)
                                || collect($filterValues)->filter(fn($value) => filled($value))->isNotEmpty();
                        @endphp
                        @if ($hasActiveFilters)
                            <div>
                                @lang('model-browser::global.no-results-filtered')
                                <button
                                    type="button"
                                    class="btn btn-link p-0 align-baseline"
                                    x-on:click="$dispatch('mb-clear-url-params'); $wire.clearFilters()"
                                >@lang('model-browser::global.reset-filters')</button>
                            </div>
                        @else
                            <div>@lang('model-browser::global.no-results')</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <x-model-browser::load-more :data="$this->rows" />

        <div class="d-flex justify-content-center align-items-start flex-wrap mt-3 gap-3">
            <x-model-browser::csv-buttons :$exportLimit />
            <x-model-browser::copy-page-button />
        </div>

    </div>
</div>
