{{--
    The statistics menu of one column: an info icon in the header that opens
    the statistics in the groups of BaseModelBrowser::STATS_GROUPS, divided by
    a line, then the column's most frequent values (or why they are not
    listed), with a button copying the lot to the clipboard. A heading names
    the list and the column, so a copy says what it is about.

    Each row is a term, a value and a second value: a share of the rows, or
    the value without zeros under the header row of the numeric group. A wide
    value takes both columns, and a full row (a header saying there are no
    zeros) all three. When the column
    has no zero, a note stands in for the non-zero group.

    The rows are rendered server-side (see BaseModelBrowser::columnStatsRows
    and columnValueRows) inside the header's "stats" island, so they arrive
    with the statistics and without re-running the data query. Opening a menu
    before they have asks for them, and the menu shows a spinner meanwhile;
    the island morph then fills it in, leaving its own attributes (the place
    and visibility set by the script) alone.
    The panel itself is `position: fixed` and placed on open: the header cell
    and the scroller around the table both clip their overflow, and a menu laid
    out inside them would be cut off.
--}}
@props(['label' => '', 'listTitle' => '', 'rows' => [], 'values' => ['message' => null, 'rows' => [], 'align' => 'start'], 'loaded' => false, 'overLimit' => false, 'limit' => 0])

<span
    class="model-browser__stats"
    x-data="{
        open: false,
        placed: false,
        copied: false,
        {{--
            Kept within the visual viewport: on a phone, a page wider than the
            screen widens the layout viewport (and innerWidth) beyond what is seen.
            The menu is moved to the visible left edge before it is measured, so
            its width is not squeezed by the space right of the toggle.
        --}}
        place() {
            const gap = 8;
            const viewport = window.visualViewport;
            const left = viewport ? viewport.offsetLeft : 0;
            const top = viewport ? viewport.offsetTop : 0;
            const width = viewport ? viewport.width : document.documentElement.clientWidth;
            const height = viewport ? viewport.height : document.documentElement.clientHeight;
            const button = $refs.toggle.getBoundingClientRect();
            const menu = $refs.menu;

            menu.style.maxWidth = `${width - 2 * gap}px`;
            menu.style.maxHeight = `${height - 2 * gap}px`;
            menu.style.left = `${left + gap}px`;
            menu.style.top = `${top + gap}px`;

            let y = button.bottom + 4;
            if (y + menu.offsetHeight > top + height - gap) {
                y = button.top - 4 - menu.offsetHeight;
            }
            menu.style.top = `${Math.max(top + gap, Math.min(y, top + height - gap - menu.offsetHeight))}px`;
            menu.style.left = `${Math.max(left + gap, Math.min(button.left, left + width - menu.offsetWidth - gap))}px`;
        },
        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.placed = false;

                $nextTick(() => {
                    this.place();
                    this.placed = true;
                });
            }
        },
        {{--
            Copied as shown. Tabs and newlines are the row/column structure of
            the copied text, and non-breaking spaces become plain ones. A row is
            a term and every description up to the next one, under the heading.
        --}}
        text() {
            const plain = (element) => element.textContent.replace(/\s+/g, ' ').trim();
            const cells = (term) => {
                const row = [plain(term)];
                for (let cell = term.nextElementSibling; cell && cell.tagName === 'DD'; cell = cell.nextElementSibling) {
                    row.push(plain(cell));
                }
                return row.join('\t');
            };

            return [
                plain($refs.menu.querySelector('.model-browser__stats-heading')),
                ...[...$refs.menu.querySelectorAll('dt')].map(cells),
            ].join('\n');
        },
        {{--
            execCommand is the fallback for insecure contexts (plain http),
            where navigator.clipboard is unavailable.
        --}}
        copyLegacy(text) {
            const field = document.createElement('textarea');
            field.style.position = 'fixed';
            field.style.left = '-9999px';
            field.value = text;
            document.body.appendChild(field);
            field.select();

            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (error) {
                console.error('Failed to copy stats:', error);
            }

            field.remove();

            return copied;
        },
        async copy() {
            const text = this.text();

            let copied = false;
            if (window.isSecureContext && navigator.clipboard) {
                try {
                    await navigator.clipboard.writeText(text);
                    copied = true;
                } catch (error) {
                    console.error('Failed to copy stats:', error);
                }
            }

            if (! copied) {
                copied = this.copyLegacy(text);
            }

            if (! copied) return;

            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    x-on:stats-opened.window="if ($event.detail !== $refs.toggle) open = false"
    {{-- Capture, so the table's own horizontal scroller closes it too. --}}
    x-on:scroll.window.capture="open = false"
    x-on:resize.window="open = false"
    x-on:mb-refresh-stats.window="open = false"
    x-on:mb-stats-loaded.window="if (open) $nextTick(() => place())"
>
    <button
        type="button"
        @class([
            'model-browser__stats-toggle',
            'model-browser__stats-toggle--ready' => $loaded && ! $overLimit,
            'model-browser__stats-toggle--unavailable' => $overLimit,
        ])
        x-bind:class="{ 'active': open }"
        x-ref="toggle"
        x-on:click.stop="
            if (!open) $dispatch('stats-opened', $el);
            toggle();
            if (open) $dispatch('mb-load-stats');
        "
        title="{{ trim(__('model-browser::global.stats.title') . ' — ' . strip_tags((string) $label), ' —') }}"
    >
        <i class="fa-solid fa-chart-simple"></i>
    </button>

    <div
        class="model-browser__stats-menu"
        x-ref="menu"
        wire:ignore.self
        x-show="open"
        x-on:click.stop
        x-bind:style="{ visibility: placed ? 'visible' : 'hidden' }"
        style="display: none;"
    >
        <p class="model-browser__stats-heading">
            @if ($listTitle)
                {{ $listTitle }} /
            @endif
            <strong>{{ strip_tags((string) $label) }}</strong>
        </p>
        @if ($overLimit)
            <p class="model-browser__stats-note">@lang('model-browser::global.stats.limit-exceeded', ['limit' => Illuminate\Support\Number::format($limit)])</p>
        @elseif ($loaded)
            <dl class="model-browser__stats-list">
                @foreach ($rows as $row)
                    @php($cell = ['model-browser__stats-group-start' => ! $loop->first && $row['group'] !== $rows[$loop->index - 1]['group'], 'model-browser__stats-header' => $row['header']])
                    <dt @class([...$cell, 'model-browser__stats-full' => $row['full']])>{{ $row['label'] }}</dt>
                    @if ($row['wide'])
                        <dd @class([...$cell, 'model-browser__stats-wide' => true])>{!! $row['display'] !!}</dd>
                    @elseif (! $row['full'])
                        <dd @class($cell)>{!! $row['display'] !!}</dd>
                        <dd @class($cell)>{!! $row['second'] !!}</dd>
                    @endif
                @endforeach
            </dl>
            @if ($values['message'])
                <p class="model-browser__stats-note model-browser__stats-note--group">{{ $values['message'] }}</p>
            @elseif ($values['rows'])
                <dl class="model-browser__stats-list model-browser__stats-values text-{{ $values['align'] }}">
                    @foreach ($values['rows'] as $row)
                        <dt title="{{ $row['title'] }}">{!! $row['label'] !!}</dt>
                        <dd>{{ $row['count'] }}</dd>
                        <dd>{{ $row['share'] }}</dd>
                    @endforeach
                </dl>
            @endif
            <button type="button" class="model-browser__stats-copy" x-on:click="copy()">
                {{--
                    The icons are toggled via x-show on wrapper spans (not by swapping
                    classes) because FontAwesome's SVG replacement (dom.watch) swaps
                    the <i> for an <svg>, which breaks Alpine class bindings on it.
                --}}
                <span x-show="!copied"><i class="fa-solid fa-fw fa-copy pe-1"></i>@lang('model-browser::global.stats.copy')</span>
                <span x-show="copied" style="display: none"><i class="fa-solid fa-fw fa-check text-success pe-1"></i>@lang('model-browser::global.stats.copied')</span>
            </button>
        @else
            <div class="model-browser__stats-loading">
                <span class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">@lang('model-browser::global.stats.loading')</span>
                </span>
            </div>
        @endif
    </div>
</span>
