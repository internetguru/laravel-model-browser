<div>
    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
        <x-model-browser::filter :$filter />
        <x-ig::input type="select" name="sort" name="sort" :options="$viewAttributes" wire:model.live="sortBy">@lang('model-browser::global.sort.by')</x-ig::input>
        <x-ig::input type="select" name="order" name="order" :options="[
            ['name' => __('model-browser::global.sort.ascending'), 'id' => 'asc'],
            ['name' => __('model-browser::global.sort.descending'), 'id' => 'desc'],
        ]" wire:model.live="sortDirection">@lang('model-browser::global.sort.direction')</x-ig::input>
    </div>

    <div class="my-5">
        <x-model-browser::pagination :$data />
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-stretch justify-items-start justify-content-center mb-4">
        @if (! empty($data->items()))
            @foreach($data as $row)
                <dl class="card" style="max-width: 25em; margin: 0; padding: 1em;">
                    @foreach($viewAttributes as $column)
                        <dt>{{ $column }}</dt>
                        <dd>{!! prettyPrint($row->$column) !!}</dd>
                    @endforeach
                </dl>
            @endforeach
        @else
            <p>@lang('model-browser::global.no-results')</p>
        @endif
    </div>

    <div class="my-5">
        <x-model-browser::pagination :$data />
    </div>

</div>
