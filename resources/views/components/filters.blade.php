@props(['filterConfig', 'filterValues', 'searchQuery' => '', 'placeholder' => null, 'submitIcon' => 'fa-magnifying-glass'])

@php
    $urlParams = collect($filterConfig)->pluck('url')->filter()->values()->toArray();
    $searchableLabels = collect($filterConfig)
        ->filter(fn($c) => ($c['type'] ?? 'string') === 'string')
        ->map(fn($c) => mb_strtolower($c['label'] ?? ''))
        ->values()
        ->implode(', ');
    $placeholder ??= __('model-browser::global.filters.search');
    // Checkboxes get their own row under the input grid, so they never sit in a grid cell
    $checkboxConfig = collect($filterConfig)->filter(fn($c) => ($c['type'] ?? 'string') === 'checkbox')->all();
    $inputConfig = collect($filterConfig)->filter(fn($c) => ($c['type'] ?? 'string') !== 'checkbox')->all();
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
                // Keep the existing state — Livewire stores the tracked query
                // string values there for back/forward navigation.
                window.history.replaceState(window.history.state, '', url.toString());
            },
        }"
        x-on:mb-clear-url-params.window="clearUrlParams()"
        x-on:click.outside="expanded = false"
        wire:ignore.self
        class="mb-search"
    >
        {{-- Search bar - always visible --}}
        <div class="mb-search__bar">
            <form wire:submit.prevent="applySearch" class="mb-search__form editable-skip">
                <input
                    type="text"
                    name="mb-search"
                    class="mb-search__input"
                    wire:model="searchQuery"
                    placeholder="{{ $placeholder }}"
                    maxlength="500"
                    autocomplete="on"
                />
                <button
                    type="button"
                    class="mb-search__btn"
                    x-show="$wire.searchQuery"
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
                    <i class="fas fa-fw {{ $submitIcon }}"></i>
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
                    @foreach ($inputConfig as $attr => $config)
                        @php
                            $type = $config['type'] ?? 'string';
                            $label = $config['label'] ?? $attr;
                            $options = $config['options'] ?? [];
                            $inputType = match($type) {
                                'date' => 'date',
                                'number' => 'number',
                                'options' => 'select',
                                default => 'text',
                            };
                            $filterPlaceholder = $type === 'string' ? __('model-browser::global.filters.search') : '';
                            $attrName = "filter-$attr";
                            $modelName = "filterValues.$attr";
                            $noAll = !empty($config['noAll']);

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
                                    :options="$noAll ? $options : ['' => __('model-browser::global.filters.all')] + $options"
                                    :useoptionkeys="true"
                                    :wire:model="$modelName"
                                >{{ $label }}</x-ig::input>
                            @elseif (in_array($type, \Internetguru\ModelBrowser\Components\BaseModelBrowser::RANGE_TYPES, true))
                                {{-- The two bounds are joined into the filter's one value, e.g. 1000..2000 --}}
                                <div
                                    class="mb-filters__range"
                                    x-data="{
                                        from: '',
                                        to: '',
                                        written: ['', ''],
                                        shown: ['', ''],
                                        init() {
                                            this.split($wire.$get(@js($modelName)));
                                            $wire.$watch(@js($modelName), value => this.split(value));
                                        },
                                        split(value) {
                                            const [from, ...rest] = String(value ?? '').split('..');
                                            this.written = [from.trim(), (rest.length ? rest.join('..') : from).trim()];
                                            // No value at all has nothing to show in a number or date input
                                            this.shown = this.written.map((bound, i) => bound === @js(\Internetguru\ModelBrowser\Components\BaseModelBrowser::FILTER_EMPTY)
                                                ? ''
                                                : (@js($type === 'date') ? this.fullDate(bound, i === 1) : bound));
                                            [this.from, this.to] = this.shown;
                                            // Lets the date inputs' floating labels follow a value set without typing
                                            this.$nextTick(() => this.$root.querySelectorAll('input').forEach(
                                                input => input.dispatchEvent(new Event('change', { bubbles: true }))
                                            ));
                                        },
                                        join() {
                                            // A bound left as shown keeps what was written, e.g. 2026-10 or 3 days ago
                                            const bound = (input, i) => input === this.shown[i] ? this.written[i] : input;
                                            const [from, to] = [bound(this.from, 0), bound(this.to, 1)];
                                            $wire.$set(@js($modelName), from === to ? from : from + '..' + to, false);
                                        },
                                        // The day a date input shows for a bound: the first or the last day of its year, month or week
                                        fullDate(bound, isUpper) {
                                            const pad = n => String(n).padStart(2, '0');
                                            const iso = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                                            const period = (start, end) => iso(isUpper ? end : start);
                                            const month = (year, index) => period(new Date(year, index, 1), new Date(year, index + 1, 0));
                                            const text = bound.toLowerCase();
                                            const today = new Date();
                                            let match;
                                            if ((match = text.match(/^(\d{4})$/))) return period(new Date(+match[1], 0, 1), new Date(+match[1], 11, 31));
                                            if ((match = text.match(/^(\d{4})-(\d{1,2})$/))) return month(+match[1], match[2] - 1);
                                            if ((match = text.match(/^(\d{1,2})[.\/](\d{4})$/))) return month(+match[2], match[1] - 1);
                                            if ((match = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/))) return iso(new Date(+match[1], match[2] - 1, +match[3]));
                                            if ((match = text.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/))) return iso(new Date(+match[3], match[2] - 1, +match[1]));
                                            const days = { today: 0, yesterday: -1, tomorrow: 1 }[text];
                                            if (days !== undefined) return iso(new Date(today.getFullYear(), today.getMonth(), today.getDate() + days));
                                            if (!(match = text.match(/^(\d+) (day|week|month|year)s? ago$/))) return '';
                                            const count = +match[1];
                                            if (match[2] === 'day') return iso(new Date(today.getFullYear(), today.getMonth(), today.getDate() - count));
                                            if (match[2] === 'month') return month(today.getFullYear(), today.getMonth() - count);
                                            if (match[2] === 'year') return period(new Date(today.getFullYear() - count, 0, 1), new Date(today.getFullYear() - count, 11, 31));
                                            const monday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 7 * count - (today.getDay() + 6) % 7);
                                            return period(monday, new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6));
                                        },
                                    }"
                                    wire:ignore
                                >
                                    @foreach (['from', 'to'] as $bound)
                                        <x-ig::input
                                            :type="$inputType"
                                            :name="$attrName . '-' . $bound"
                                            value=""
                                            :x-model="$bound"
                                            x-on:input="join()"
                                            :showError="false"
                                            :step="$inputType === 'number' ? 'any' : null"
                                        >{{ __('model-browser::global.filters.range-' . $bound, ['label' => $label]) }}</x-ig::input>
                                    @endforeach
                                </div>
                                @error($attrName)
                                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            @else
                                <x-ig::input
                                    :type="$inputType"
                                    :name="$attrName"
                                    :value="$filterValues[$attr] ?? ''"
                                    :placeholder="$filterPlaceholder"
                                    :wire:model="$modelName"
                                    :step="$inputType === 'number' ? 'any' : null"
                                >{{ $label }}</x-ig::input>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Checkboxes - own line under the fields --}}
                @if (!empty($checkboxConfig))
                    <div class="mb-filters__checkboxes">
                        @foreach ($checkboxConfig as $attr => $config)
                            @php
                                $attrName = "filter-$attr";
                                $modelName = "filterValues.$attr";
                            @endphp
                            <div class="mb-filters__item">
                                <x-ig::input
                                    type="checkbox"
                                    :name="$attrName"
                                    :value="1"
                                    :checked="(bool) ($filterValues[$attr] ?? false)"
                                    :wire:model="$modelName"
                                >{{ $config['label'] ?? $attr }}</x-ig::input>
                            </div>
                        @endforeach
                    </div>
                @endif

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
