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
                if (column === $wire.sortBy) {
                    if ($wire.sortDirection === 'asc') {
                        $wire.set('sortDirection', 'desc');
                    } else if ($wire.sortDirection === 'desc') {
                        $wire.set('sortDirection', '');
                        $wire.set('sortBy', '');
                    } else {
                        $wire.set('sortDirection', 'asc');
                    }
                } else {
                    $wire.set('sortBy', column);
                    $wire.set('sortDirection', 'asc');
                }
            }
        }"
    >

        <div class="d-flex justify-content-end alig-items-center gap-3 m-3">
            <x-model-browser::filter :$filter />
            <div class="mt-3">
                <x-model-browser::fullscreen-button />
            </div>
        </div>

        <div class="my-5">
            <x-model-browser::pagination :$data />
        </div>

        <div class="table-responsive">
            <table class="table table-borderless">
                <thead>
                    <tr style="--bs-border-color: #ced6e0;" class="border-bottom">
                        @foreach($viewAttributes as $column => $trans)
                            <th class="table-light" @if($enableSort) x-on:click="sortColumn('{{ $column }}')" @endif>
                                <span class="d-flex align-items-center gap-1" @if($enableSort)style="cursor: pointer;"@endif>
                                    {{ $trans }}
                                    @if($enableSort && $sortBy === $column)
                                        <i @class([
                                            "fas fa-fw",
                                            "fa-up-long" => $sortDirection === 'asc',
                                            "fa-down-long" => $sortDirection === 'desc',
                                        ])></i>
                                    @elseif($enableSort)
                                        <i class="fas fa-fw fa-up-down"></i>
                                    @endif
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if (! empty($data->items()))
                        @foreach($data as $row)
                            <tr style="--bs-border-color: #f2f5fa;" @class([
                                'border-bottom',
                                'table-light' => ($loop->index / $lightDarkStep) % 2 == 1,
                            ])>
                                @foreach($viewAttributes as $column => $trans)
                                    <td @class([
                                        'text-' . $this->getAlignment($column, Arr::get($row, $column)),
                                    ])>{!!
                                        Arr::get($row, $column . 'Highlighted')
                                        ?? Arr::get($row, $column . 'Formatted')
                                        ?? prettyPrint(Arr::get($row, $column))
                                    !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="{{ count($viewAttributes) }}">@lang('model-browser::global.no-results')</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="my-5">
            <x-model-browser::pagination :$data />
        </div>

        <x-model-browser::csv-buttons />

    </div>
</div>
