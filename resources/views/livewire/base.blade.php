<div class="model-browser model-browser-base">
    <x-model-browser::filters :$filterConfig :$filterValues :$searchQuery />

    @island(name: 'count')
        @include('model-browser::partials.count')
    @endisland

    <div @if ($refreshInterval) wire:poll.{{ $refreshInterval }}s @endif>
        <div class="my-5">
            <x-model-browser::pagination :data="$this->rows" :$perPageOptions />
        </div>

        <div class="d-flex flex-wrap gap-3 align-items-stretch justify-items-start justify-content-center mb-4">
            @if (! empty($this->rows->items()))
                @foreach ($this->rows as $row)
                    <dl class="card" style="max-width: 25em; margin: 0; padding: 1em;">
                        @foreach ($viewAttributes as $column => $trans)
                            <dt>{{ $trans }}</dt>
                            <dd>{!! $this->itemValue($row, $column) ?: '-' !!}</dd>
                        @endforeach
                    </dl>
                @endforeach
            @else
                <p>@lang('model-browser::global.no-results')</p>
            @endif
        </div>
    </div>

    <x-model-browser::csv-buttons :$exportLimit />

</div>
