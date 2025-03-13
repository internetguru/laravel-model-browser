<form
    class="input-group justify-content-center mt-3 editable-skip mb-filter"
    x-data="{
        filterText: $wire.filter,
        filterColumn: 'all',
        filterColumnTrans: '{{ __('model-browser::global.filter.all') }}',
        setDropdownWidth() {
            this.$nextTick(() => {
                const dropdownItems = document.querySelectorAll('.filter-dropdown-item');
                const buttonSpan = this.$refs.dropdownButtonSpan;
                const originalText = buttonSpan.textContent;
                let maxWidth = 0;
                dropdownItems.forEach(item => {
                    buttonSpan.textContent = item.textContent;
                    maxWidth = Math.max(maxWidth, buttonSpan.offsetWidth);
                });
                buttonSpan.textContent = originalText;
                this.$refs.dropdownButton.style.minWidth = (maxWidth + 60) + 'px';
            });
        }
    }"
    wire:submit.prevent="$set('filter', filterText); $wire.set('filterColumn', filterColumn)"
    x-init="setDropdownWidth()"
>
    <span class="position-relative">
        <input
            type="text"
            name="filter"
            x-ref="filter"
            x-model="filterText"
            class="form-control py-3 pe-4"
        ></input>
        <i
            class="fas fa-fw fa-close position-absolute top-50 end-0 translate-middle-y p-2 z-2"
            style="cursor: pointer;"
            x-show="filterText"
            x-on:click="filterText = ''; $refs.filter.focus();"
        ></i>
        <i
            class="fa-solid fa-fw fa-filter position-absolute top-50 end-0 translate-middle-y p-2 z-1"
            x-show="!filterText"
        ></i>
    </span>
    <div class="d-flex">
        <button type="submit" class="btn btn-primary">
            @lang('model-browser::global.filter.placeholder')
        </button>
        <button
            wire:ignore.self
            type="button"
            class="btn btn-primary dropdown-toggle"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            x-ref="dropdownButton"
        >
            <span x-text="filterColumnTrans" x-ref="dropdownButtonSpan"></span>
        </button>
        <ul class="dropdown-menu">
            <li>
                <a class="dropdown-item filter-dropdown-item" href="#" x-on:click.prevent="filterColumn = 'all'; filterColumnTrans = '{{ __('model-browser::global.filter.all') }}'">
                    @lang('model-browser::global.filter.all')
                </a>
            </li>
            @foreach($viewAttributes as $column => $trans)
                <li>
                    <a class="dropdown-item filter-dropdown-item" href="#" x-on:click.prevent="filterColumn = '{{ $column }}', filterColumnTrans = '{{ $trans }}'">
                        {{ $trans }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</form>
