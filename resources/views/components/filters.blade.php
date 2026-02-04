@props(['filterConfig', 'filterValues'])

@php
    $activeFilters = $this->getActiveFilters();
    $hasActive = !empty($activeFilters);
@endphp

@if (!empty($filterConfig))
    <div
        x-data="{ expanded: false }"
        wire:ignore.self
        class="mb-filters mb-3 px-3"
    >
        {{-- Buttons --}}
        <div class="mb-2 d-flex flex-wrap gap-3 align-items-center justify-content-start">
            <button
                type="button"
                class="btn btn-shadow btn-white btn-primary"
                x-on:click="expanded = !expanded"
            >
                <i class="fas fa-fw fa-chevron-down" x-show="!expanded"></i>
                <i class="fas fa-fw fa-chevron-up" x-show="expanded" style="display: none;"></i>
                <span x-text="expanded ? '@lang('model-browser::global.filters.hide')' : '@lang('model-browser::global.filters.show')'"></span>
            </button>
            @if ($hasActive)
                <button
                    type="button"
                    class="btn btn-shadow btn-white btn-danger"
                    wire:click="clearFilters"
                >
                    @lang('model-browser::global.filters.clear-all')
                </button>
            @endif
        </div>

        {{-- Filters --}}
        <div class="d-flex gap-3 align-items-end justify-content-start">
            @foreach ($filterConfig as $attr => $config)
                @php
                    $isActive = isset($activeFilters[$attr]);
                    $type = $config['type'] ?? 'string';
                    $label = $config['label'] ?? $attr;
                    $options = $config['options'] ?? [];
                    $inputType = match($type) {
                        'date', 'date_from', 'date_to' => 'date',
                        'number', 'number_from', 'number_to' => 'number',
                        'options' => 'select',
                        default => 'text',
                    };
                    $placeholder = match($type) {
                        'number_from' => __('model-browser::global.filters.from'),
                        'number_to' => __('model-browser::global.filters.to'),
                        'string' => __('model-browser::global.filters.search'),
                        default => '',
                    };
                @endphp
                <div
                    class="mb-filter-item"
                    :class="{ 'mb-filter-active': expanded && {{ $isActive ? 'true' : 'false' }} }"
                    @if (!$isActive) x-show="expanded" @endif
                >
                    @if ($inputType === 'select')
                        <x-ig::input
                            type="select"
                            name="filter-{{ $attr }}"
                            :value="$filterValues[$attr] ?? ''"
                            :options="['' => __('model-browser::global.filters.all')] + $options"
                            :useoptionkeys="true"
                            :clearable="false"
                            :showError="false"
                            wire:model.live.debounce.300ms="filterValues.{{ $attr }}"
                        >{{ $label }}</x-ig::input>
                    @else
                        <x-ig::input
                            :type="$inputType"
                            name="filter-{{ $attr }}"
                            :value="$filterValues[$attr] ?? ''"
                            :clearable="false"
                            :showError="false"
                            :placeholder="$placeholder"
                            wire:model.live.debounce.300ms="filterValues.{{ $attr }}"
                        >{{ $label }}</x-ig::input>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
