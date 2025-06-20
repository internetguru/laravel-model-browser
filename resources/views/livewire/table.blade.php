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
            sortColumn: function(column, first = 0) {
                let current = $wire.sort[column] || (first ? $wire.defaultSort[column] : null) || null;
                if (current === 'asc') {
                    $wire.set('sort', { [column]: 'desc' });
                } else if (current === 'desc') {
                    let data = {}
                    if ($wire.defaultSort[column] || false) {
                        data[column] = 'asc';
                    }
                    $wire.set('sort', data);
                } else {
                    $wire.set('sort', { [column]: 'asc' });
                }
            }
        }"
    >

        <div class="d-flex justify-content-end alig-items-center gap-3 m-3">
            @if (!empty ($this->filterAttributes))
                <x-model-browser::filter :$filter :$viewAttributes />
            @endif
            <div class="mt-3">
                <x-model-browser::fullscreen-button />
            </div>
        </div>

        <div class="my-5">
            <x-model-browser::pagination :$data />
        </div>

        <div class="table-responsive">
            <div class="grid-table" style="grid-template-columns: {{ $this->generateGridColumns() }};">
                <div class="grid-header">
                    @php
                        $first = true;
                        $columnCount = count($viewAttributes);
                    @endphp
                    @foreach($viewAttributes as $column => $trans)
                        <div class="grid-header-cell">
                            <span class="d-flex align-items-center gap-1">
                                @php
                                    $currentDirection = $sort[$column] ?? null;
                                    if ($first && empty($sort)) {
                                        $currentDirection = $defaultSort[$column] ?? null;
                                    }
                                @endphp
                                @if ($enableSort)
                                    <span x-on:click="sortColumn('{{ $column }}', {{ $first ? 1 : 0 }})" style="cursor: pointer;">
                                        @if ($currentDirection)
                                            <i @class([
                                                "fas fa-fw",
                                                "fa-up-long" => $currentDirection === 'asc',
                                                "fa-down-long" => $currentDirection === 'desc',
                                            ])></i>
                                        @else
                                            <i class="fas fa-fw fa-up-down"></i>
                                        @endif
                                    </span>
                                @endif
                                {{ $trans }}
                                @php
                                    if ($enableSort && $currentDirection) {
                                        $first = false;
                                    }
                                @endphp
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
                                    $this->itemValueHighlighted($row, $column)
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

        <div class="my-5">
            <x-model-browser::pagination :$data />
        </div>

        <x-model-browser::csv-buttons />

    </div>
</div>
