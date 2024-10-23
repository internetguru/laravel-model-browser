<div wire:lazy>
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

        <table class="table table-borderless">
            <thead>
                <tr class="table-light">
                    @foreach($viewAttributes as $column => $trans)
                        <th x-on:click="sortColumn('{{ $column }}')">
                            <span class="d-flex align-items-center gap-1" style="cursor: pointer;">
                                {{ $trans }}
                                @if($sortBy === $column)
                                    <i @class([
                                        "fas fa-fw",
                                        "fa-up-long" => $sortDirection === 'asc',
                                        "fa-down-long" => $sortDirection === 'desc',
                                    ])></i>
                                @else
                                    <i class="fas fa-fw fa-up-down text-black" style="--bs-text-opacity: .2;"></i>
                                @endif
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if (! empty($data->items()))
                    @foreach($data as $row)
                        <tr @class(['table-light' => ($loop->index / $lightDarkStep) % 2 >= 1])>
                            @foreach($viewAttributes as $column => $trans)
                                <td>{!!
                                    isset($formats[$column])
                                        ? Arr::get($row, $column)
                                        : prettyPrint(Arr::get($row, $column))
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

        <div class="my-5">
            <x-model-browser::pagination :$data />
        </div>

    </div>
</div>
