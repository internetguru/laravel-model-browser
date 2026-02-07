@props(['filterConfig', 'filterValues'])

@php
    $activeFilters = $this->getActiveFilters();
    $hasActive = !empty($activeFilters);
    $activeKeys = array_keys($activeFilters);
    $urlParams = collect($filterConfig)->pluck('url')->filter()->values()->toArray();
@endphp

@if (!empty($filterConfig))
    <div
        x-data="{
            expanded: false,
            activeFilters: @js($activeKeys),
            isActive(attr) {
                return this.activeFilters.includes(attr);
            },
            clearUrlParams() {
                const params = @js($urlParams);
                if (params.length === 0) return;

                const url = new URL(window.location.href);
                params.forEach(param => url.searchParams.delete(param));
                window.history.replaceState({}, '', url.toString());
            },
            clearActive() {
                this.activeFilters = [];
            }
        }"
        x-on:mb-clear-url-params.window="clearUrlParams()"
        x-on:mb-filters-applied.window="activeFilters = $event.detail.active"
        wire:ignore.self
        class="mb-filters"
    >
        {{-- Toggle button --}}
        <div class="mb-filters__toggle">
            <button
                type="button"
                class="btn btn-shadow btn-white btn-primary"
                x-on:click="expanded = !expanded"
            >
                <i class="fas fa-fw fa-chevron-down" x-show="!expanded"></i>
                <i class="fas fa-fw fa-chevron-up" x-show="expanded" style="display: none;"></i>
                @lang('model-browser::global.filters.label')
                <span
                    class="badge bg-primary ms-1"
                    x-show="activeFilters.length > 0"
                    x-text="activeFilters.length"
                ></span>
            </button>
        </div>

        {{-- Filters --}}
        <form wire:submit.prevent="applyFilters" x-show="expanded || activeFilters.length > 0" x-cloak>
            <div class="mb-filters__fields">
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
                        $attrName = "filter-$attr";
                        $modelName = "filterValues.$attr";

                        // Skip options filters with only one option
                        if ($inputType === 'select' && count($options) <= 1) {
                            continue;
                        }
                    @endphp
                    <div
                        class="mb-filters__item"
                        :class="{ 'mb-filters__item--active': isActive('{{ $attr }}') }"
                        x-show="expanded || isActive('{{ $attr }}')"
                    >
                        @if ($inputType === 'select')
                            <x-ig::input
                                type="select"
                                :name="$attrName"
                                :value="$filterValues[$attr] ?? ''"
                                :options="['' => __('model-browser::global.filters.all')] + $options"
                                :useoptionkeys="true"
                                :wire:model="$modelName"
                            >{{ $label }}</x-ig::input>
                        @else
                            <x-ig::input
                                :type="$inputType"
                                :name="$attrName"
                                :value="$filterValues[$attr] ?? ''"
                                :clearable="false"
                                :placeholder="$placeholder"
                                :wire:model="$modelName"
                            >{{ $label }}</x-ig::input>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Action buttons --}}
            <div class="mb-filters__actions">
                <button
                    type="button"
                    class="btn btn-shadow btn-white btn-danger"
                    x-on:click="clearUrlParams(); clearActive(); $wire.clearFilters()"
                    :disabled="activeFilters.length === 0"
                >
                    <i class="fas fa-fw fa-xmark"></i>
                    @lang('model-browser::global.filters.clear-all')
                </button>
                <button
                    type="submit"
                    class="btn btn-shadow btn-white btn-success"
                >
                    <i class="fas fa-fw fa-check"></i>
                    @lang('model-browser::global.filters.apply')
                </button>
            </div>
        </form>
    </div>
@endif
