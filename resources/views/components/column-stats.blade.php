{{--
    The statistics menu of one column: an info icon in the header that opens
    SUM / AVG / MIN / MAX / COUNT and their non-zero counterparts, with a
    button copying the lot to the clipboard.

    The rows are rendered server-side (see BaseModelBrowser::columnStatsRows)
    inside the header's "stats" island, so they arrive with the statistics and
    without re-running the data query. Until they have, the icon is disabled.
    The panel itself is `position: fixed` and placed on open: the header cell
    and the scroller around the table both clip their overflow, and a menu laid
    out inside them would be cut off.
--}}
@props(['label' => '', 'rows' => [], 'loaded' => false, 'overLimit' => false, 'limit' => 0])

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
        {{-- Tabs and newlines are the row/column structure of the copied text. --}}
        text() {
            return [...$refs.menu.querySelectorAll('dt')].map((term) => {
                const value = term.nextElementSibling;
                const raw = value.getAttribute('data-raw');

                return term.textContent.trim() + '\t' + (raw !== null ? raw : value.textContent.trim()).replace(/\s+/g, ' ');
            }).join('\n');
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
>
    <button
        type="button"
        class="model-browser__stats-toggle"
        x-bind:class="{ 'active': open }"
        x-ref="toggle"
        @disabled(! $loaded && ! $overLimit)
        x-on:click.stop="
            if (!open) $dispatch('stats-opened', $el);
            toggle();
        "
        title="{{ trim(__('model-browser::global.stats.title') . ' — ' . strip_tags((string) $label), ' —') }}"
    >
        <i class="fa-solid fa-chart-simple"></i>
    </button>

    <div
        class="model-browser__stats-menu"
        x-ref="menu"
        x-show="open"
        x-on:click.stop
        x-bind:style="{ visibility: placed ? 'visible' : 'hidden' }"
        style="display: none;"
    >
        @if ($overLimit)
            <p class="model-browser__stats-note">@lang('model-browser::global.stats.limit-exceeded', ['limit' => Illuminate\Support\Number::format($limit)])</p>
        @elseif ($loaded)
            <dl class="model-browser__stats-list">
                @foreach ($rows as $row)
                    <dt>{{ $row['label'] }}</dt>
                    <dd data-raw="{{ $row['raw'] }}">{!! $row['display'] !!}</dd>
                @endforeach
            </dl>
            <button type="button" class="model-browser__stats-copy" x-on:click="copy()">
                {{--
                    The icons are toggled via x-show on wrapper spans (not by swapping
                    classes) because FontAwesome's SVG replacement (dom.watch) swaps
                    the <i> for an <svg>, which breaks Alpine class bindings on it.
                --}}
                <span x-show="!copied"><i class="fa-solid fa-fw fa-copy pe-1"></i>@lang('model-browser::global.stats.copy')</span>
                <span x-show="copied" style="display: none"><i class="fa-solid fa-fw fa-check text-success pe-1"></i>@lang('model-browser::global.stats.copied')</span>
            </button>
        @endif
    </div>
</span>
