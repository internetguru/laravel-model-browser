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
                    $wire.set('sort', {} );
                } else {
                    $wire.set('sort', { [column]: 'asc' });
                }
            }
        }"
    >

        <div class="d-flex justify-content-end alig-items-center gap-3 m-3">
            <x-model-browser::filter :$filter :$viewAttributes />
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
                        @php
                            $first = true;
                        @endphp
                        @foreach($viewAttributes as $column => $trans)
                            <th class="table-light" @if($enableSort) x-on:click="sortColumn('{{ $column }}', {{ $first ? 1 : 0 }})" @endif>
                                <span class="d-flex align-items-center gap-1" @if($enableSort) style="cursor: pointer;" @endif>
                                    @php
                                        $currentDirection = $sort[$column] ?? null;
                                        if ($first && empty($sort)) {
                                            $currentDirection = $defaultSort[$column] ?? null;
                                            $first = false;
                                        }
                                    @endphp
                                    @if($enableSort && $currentDirection)
                                        <i @class([
                                            "fas fa-fw",
                                            "fa-up-long" => $currentDirection === 'asc',
                                            "fa-down-long" => $currentDirection === 'desc',
                                        ])></i>
                                    @elseif($enableSort)
                                        <i class="fas fa-fw fa-up-down"></i>
                                    @endif
                                    {{ $trans }}
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($data->isNotEmpty())
                        @foreach($data as $row)
                            <tr style="--bs-border-color: #f2f5fa;" @class([
                                'border-bottom',
                                'table-light' => ($loop->index / $lightDarkStep) % 2 == 1,
                            ])>
                                @foreach($viewAttributes as $column => $trans)
                                    <td @class([
                                        'text-' . $this->getAlignment($column, Arr::get($row, $column)),
                                    ])>{!!
                                        $this->itemValueHighlighted($row, $column) ?: '-'
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
