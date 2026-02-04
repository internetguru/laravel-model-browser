@props(['filterConfig', 'filterValues'])

@php
    $activeFilters = $this->getActiveFilters();
    $hasActive = !empty($activeFilters);
    $urlParams = collect($filterConfig)->pluck('url')->filter()->values()->toArray();
@endphp

@if (!empty($filterConfig))
    <div
        x-data="{
            expanded: false,
            clearUrlParams() {
                const params = @js($urlParams);
                if (params.length === 0) return;

                const url = new URL(window.location.href);
                params.forEach(param => url.searchParams.delete(param));
                window.history.replaceState({}, '', url.toString());
            },
            clearActive() {
                document.querySelectorAll('.mb-filter-item.mb-filter-active').forEach(el => {
                    el.classList.remove('mb-filter-active');
                });
            }
        }"
        x-on:mb-clear-url-params.window="
            clearUrlParams()
        "
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
        </div>

        {{-- Filters --}}
        <form wire:submit.prevent="applyFilters">
            <div class="d-flex gap-3 align-items-start justify-content-start flex-wrap">
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
                    @endphp
                    <div
                        class="mb-filter-item"
                        :class="{ 'mb-filter-active': expanded && {{ $isActive ? 'true' : 'false' }} }"
                        @if (!$isActive) x-show="expanded" @endif
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

            {{-- Buttons --}}
            <div
                class="mt-3 d-flex flex-wrap gap-3 align-items-center justify-content-start"
                x-show="expanded || {{ $hasActive ? 'true' : 'false' }}"
            >
                <button
                    type="button"
                    class="btn btn-shadow btn-white btn-danger"
                    x-on:click="clearUrlParams(); clearActive(); $wire.clearFilters()"
                    @disabled(!$hasActive)
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
