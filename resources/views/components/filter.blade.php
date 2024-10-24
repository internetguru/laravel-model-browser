<form
    class="input-group justify-content-center mt-3 editable-skip"
    x-data="{ filterText: $wire.filter }"
    wire:submit.prevent="$set('filter', filterText)"
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
    <button class="btn btn-ico btn-primary mt-3" type="submit">
        <i class="fas fa-filter"></i>
        @lang('model-browser::global.filter.button')
    </button>
</form>
