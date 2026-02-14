@props(['filterConfig', 'filterValues', 'searchQuery' => ''])

@php
    $urlParams = collect($filterConfig)->pluck('url')->filter()->values()->toArray();
    $searchableLabels = collect($filterConfig)
        ->filter(fn($c) => ($c['type'] ?? 'string') === 'string')
        ->map(fn($c) => mb_strtolower($c['label'] ?? ''))
        ->values()
        ->implode(', ');
@endphp

@if (!empty($filterConfig))
    <div
        x-data="{
            expanded: false,
            query: $wire.entangle('searchQuery'),
            clearUrlParams() {
                const params = @js($urlParams);
                if (params.length === 0) return;

                const url = new URL(window.location.href);
                params.forEach(param => url.searchParams.delete(param));
                window.history.replaceState({}, '', url.toString());
            },
        }"
        x-on:mb-clear-url-params.window="clearUrlParams()"
        x-on:click.outside="expanded = false"
        wire:ignore.self
        class="mb-search"
    >
        {{-- Search bar - always visible --}}
        <div class="mb-search__bar">
            <form wire:submit.prevent="applySearch" class="mb-search__form">
                <input
                    type="text"
                    class="mb-search__input"
                    x-model="query"
                    placeholder="@lang('model-browser::global.filters.search')"
                    maxlength="500"
                />
                <button
                    type="button"
                    class="mb-search__btn"
                    x-show="query"
                    x-on:click="clearUrlParams(); $wire.clearFilters()"
                    x-cloak
                    title="@lang('model-browser::global.filters.clear-all')"
                >
                    <i class="fas fa-fw fa-xmark"></i>
                </button>
                <span class="mb-search__divider"></span>
                <button
                    type="button"
                    class="mb-search__btn"
                    x-on:click="expanded = !expanded"
                    title="@lang('model-browser::global.filters.label')"
                >
                    <i class="fas fa-fw fa-sliders"></i>
                </button>
                <button type="submit" class="mb-search__btn mb-search__btn--submit">
                    <i class="fas fa-fw fa-magnifying-glass"></i>
                </button>
            </form>
            {{-- @if ($searchableLabels)
                <div class="mb-search__hint">
                    @lang('model-browser::global.filters.search-hint', ['fields' => $searchableLabels])
                </div>
            @endif --}}
        </div>

        {{-- Filter fields - overlay --}}
        <div
            class="mb-filters__overlay"
            style="display: none;"
            x-show="expanded"
        >
            <form x-on:submit.prevent="expanded = false; $wire.applyFilters()" class="editable-skip">
                <div class="mb-filters__fields">
                    @foreach ($filterConfig as $attr => $config)
                        @php
                            $type = $config['type'] ?? 'string';
                            $label = $config['label'] ?? $attr;
                            $options = $config['options'] ?? [];
                            $inputType = match($type) {
                                'date', 'date_from', 'date_to' => 'date',
                                'number', 'number_from', 'number_to' => 'number',
                                'options' => 'select',
                                default => 'text',
                            };
                            $filterPlaceholder = match($type) {
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
                        <div class="mb-filters__item">
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
                                    :placeholder="$filterPlaceholder"
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
                        x-on:click="expanded = false; clearUrlParams(); $wire.clearFilters()"
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
    </div>
@endif
