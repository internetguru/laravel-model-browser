<div class="model-browser model-browser-base">
    <x-model-browser::filters :$filterConfig :$filterValues :$searchQuery :search-name="$this->searchInputName()" />

    <x-model-browser::pagination :data="$this->rows" :$skip>
        <x-slot:count>
            @island(name: 'count', always: true)
                @include('model-browser::partials.count')
            @endisland
        </x-slot:count>
    </x-model-browser::pagination>

    @if ($refreshInterval)
        <x-ig::watch :interval="$refreshInterval" />
    @endif

    <div>

        <div class="d-flex flex-wrap gap-3 align-items-stretch justify-items-start justify-content-center mb-4">
            @if (! empty($this->rows->items()))
                @php $rowKeys = $this->rowKeys($this->rows); @endphp
                @foreach ($this->rows as $row)
                    <dl wire:key="{{ $rowKeys[$loop->index] }}" class="card" style="max-width: 25em; margin: 0; padding: 1em;">
                        @foreach ($viewAttributes as $column => $trans)
                            @php $rawValue = $this->itemValueRaw($row, $column); @endphp
                            <dt>{{ $trans }}</dt>
                            <dd @if ($rawValue !== null) data-raw="{{ $rawValue }}" @endif>{!! $this->itemValue($row, $column) ?: '-' !!}</dd>
                        @endforeach
                    </dl>
                @endforeach
            @else
                <p>@lang('model-browser::global.no-results')</p>
            @endif
        </div>
    </div>

    <x-model-browser::load-more :data="$this->rows" />

    <div class="d-flex justify-content-center align-items-start flex-wrap gap-3">
        <x-model-browser::csv-buttons :$exportLimit />
        <x-model-browser::copy-page-button />
    </div>

</div>
