{{--
    Copies the rows of the *current page only* to the clipboard, in both a
    plain-text (TSV) and an HTML table flavor, so it can be pasted straight
    into a spreadsheet.

    Cell values are read from the `data-raw` attribute rendered by the
    browser views (the same value the CSV export uses); the visible,
    `formats`-rendered text is only a fallback for cells without it.
--}}
<div
    x-data="{
        copied: false,
        cellValue(cell) {
            const raw = cell.getAttribute('data-raw');

            return (raw !== null ? raw : cell.textContent).trim();
        },
        {{--
            Both browser layouts are supported: the table view renders a CSS
            grid (header cells + row divs), the base view a card per row with
            a <dt>/<dd> pair per column.
        --}}
        collect() {
            const root = $el.closest('.model-browser');
            if (! root) return null;

            const grid = root.querySelector('.grid-table');
            if (grid) {
                const header = [...grid.querySelectorAll('.grid-header-cell')].map((cell) => cell.textContent.trim());
                const rows = [...grid.querySelectorAll('.grid-row')]
                    .map((row) => [...row.querySelectorAll('.grid-cell')].map((cell) => this.cellValue(cell)));

                return rows.length ? { header, rows } : null;
            }

            const cards = [...root.querySelectorAll('dl.card')];
            if (! cards.length) return null;

            const header = [...cards[0].querySelectorAll('dt')].map((term) => term.textContent.trim());
            const rows = cards.map((card) => [...card.querySelectorAll('dd')].map((cell) => this.cellValue(cell)));

            return { header, rows };
        },
        {{-- Tabs and newlines would break the TSV row/column structure. --}}
        toText(data) {
            const line = (cells) => cells.map((cell) => cell.replace(/\s+/g, ' ')).join('\t');

            return [line(data.header), ...data.rows.map(line)].join('\n');
        },
        toHtml(data) {
            const escape = (value) => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const cells = (tag, values) => values.map((value) => `<${tag}>${escape(value)}</${tag}>`).join('');

            return '<table><thead><tr>' + cells('th', data.header) + '</tr></thead><tbody>'
                + data.rows.map((row) => '<tr>' + cells('td', row) + '</tr>').join('')
                + '</tbody></table>';
        },
        {{--
            execCommand is the fallback for insecure contexts (plain http),
            where navigator.clipboard is unavailable: it copies a selection
            of an off-screen table, which keeps the HTML flavor.
        --}}
        copyLegacy(html) {
            const container = document.createElement('div');
            container.style.position = 'fixed';
            container.style.left = '-9999px';
            container.innerHTML = html;
            document.body.appendChild(container);

            const range = document.createRange();
            range.selectNode(container);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (error) {
                console.error('Failed to copy page:', error);
            }

            selection.removeAllRanges();
            container.remove();

            return copied;
        },
        async copyPage() {
            const data = this.collect();
            if (! data) return;

            const text = this.toText(data);
            const html = this.toHtml(data);

            let copied = false;
            if (window.isSecureContext && navigator.clipboard && window.ClipboardItem) {
                try {
                    await navigator.clipboard.write([new ClipboardItem({
                        'text/plain': new Blob([text], { type: 'text/plain' }),
                        'text/html': new Blob([html], { type: 'text/html' }),
                    })]);
                    copied = true;
                } catch (error) {
                    console.error('Failed to copy page:', error);
                }
            }

            if (! copied) {
                copied = this.copyLegacy(html);
            }

            if (! copied) return;

            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        }
    }"
>
    <button
        type="button"
        class="btn btn-icon btn-white btn-shadow"
        x-on:click="copyPage()"
        title="{{ __('model-browser::global.copy-page.title') }}"
    >
        {{--
            The icons are toggled via x-show on wrapper spans (not by swapping
            classes) because FontAwesome's SVG replacement (dom.watch) swaps
            the <i> for an <svg>, which breaks Alpine class bindings on it.
        --}}
        <span class="pe-2" x-show="!copied"><i class="fa-solid fa-fw fa-copy"></i></span>
        <span class="pe-2" x-show="copied" style="display: none"><i class="fa-solid fa-fw fa-check text-success"></i></span>
        @lang('model-browser::global.copy-page.label')
    </button>
</div>
