<form
    class="input-group justify-content-center mt-3 editable-skip mb-filter"
    x-data="{ filterText: $wire.filter, filterColumn: 'all', filterColumnTrans: '{{ __('model-browser::global.filter.all') }}' }"
    wire:submit.prevent="$set('filter', filterText); $wire.set('filterColumn', filterColumn)"
>
    <span class="position-relative">
        <x-ig::input
            type="text"
            name="filter"
            x-ref="filter"
            x-model="filterText"
        >@lang('model-browser::global.filter.placeholder')</x-ig::input>
        <i
            class="fas fa-fw fa-close position-absolute top-50 end-0 translate-middle-y p-2 z-1"
            style="cursor: pointer;"
            x-show="filterText"
            x-on:click="filterText = ''; $refs.filter.focus();"
        ></i>
    </span>
    <div class="btn-group">
        <button type="submit" class="btn btn-ico btn-primary">
            <i class="fa-solid fa-fw fa-filter"></i>
            @lang('model-browser::global.filter.button') <span x-text="filterColumnTrans.toLowerCase()"></span>
        </button>
        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="visually-hidden">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu">
            <li>
                <a class="dropdown-item" href="#" x-on:click.prevent="filterColumn = 'all'; filterColumnTrans = '{{ __('model-browser::global.filter.all') }}'">
                    @lang('model-browser::global.filter.all')
                </a>
            </li>
            @foreach($viewAttributes as $column => $trans)
                <li>
                    <a class="dropdown-item" href="#" x-on:click.prevent="filterColumn = '{{ $column }}', filterColumnTrans = '{{ $trans }}'">
                        {{ $trans }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</form>
