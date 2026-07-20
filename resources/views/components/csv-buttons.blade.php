{{--
    Downloads the CSV through the dedicated streaming endpoint instead of a
    Livewire action: the component's signed snapshot is POSTed as a plain form
    submission, so the browser handles the response as a native (streamed)
    file download. A client-generated token is echoed back as a cookie by the
    server, which is how the spinner knows the download has started.
--}}
@props(['exportLimit' => (int) config('model-browser.export_limit')])
<div
    class="d-flex justify-content-center mt-3"
    x-data="{
        downloading: false,
        pollTimer: null,
        currentToken: null,
        exportLimit: @js($exportLimit),
        {{--
            totalCount loads asynchronously inside the 'count' island, so the
            over-limit state must be read reactively from $wire rather than
            rendered server-side (this markup is outside that island).
        --}}
        get overLimit() {
            return this.exportLimit > 0
                && $wire.totalCount !== null
                && $wire.totalCount > this.exportLimit;
        },
        download() {
            if (this.downloading) return;
            if (this.overLimit && !confirm(@js(trans('model-browser::global.download-csv.confirm-limit', ['limit' => $exportLimit])))) {
                return;
            }
            this.downloading = true;
            const token = Date.now().toString(36) + Math.random().toString(36).slice(2);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @js(route('model-browser.download-csv'));
            form.style.display = 'none';
            const add = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            };
            add('_token', @js(csrf_token()));
            add('snapshot', $wire.__instance.snapshotEncoded);
            add('token', token);
            add('truncate', this.overLimit ? '1' : '0');
            document.body.appendChild(form);
            form.submit();
            form.remove();
            this.currentToken = token;
            const stop = () => {
                if (this.currentToken !== token) return;
                clearInterval(this.pollTimer);
                this.downloading = false;
            };
            this.pollTimer = setInterval(() => {
                if (document.cookie.includes('mb_csv_download=' + token)) {
                    document.cookie = 'mb_csv_download=; Max-Age=0; path=/';
                    stop();
                }
            }, 250);
            setTimeout(stop, 120000);
        }
    }"
>
    {{--
        When over the export limit the button only looks disabled — a real
        disabled attribute would swallow the click that triggers the
        explanatory alert in download().
    --}}
    <button
        class="btn btn-icon btn-white btn-shadow"
        x-on:click="download()"
        x-bind:disabled="downloading"
        x-bind:style="overLimit ? 'opacity: .65; cursor: not-allowed;' : ''"
    >
        {{--
            The icons are toggled via x-show on wrapper spans (not by swapping
            classes) because FontAwesome's SVG replacement (dom.watch) swaps
            the <i> for an <svg>, which breaks Alpine class bindings on it.
        --}}
        {{--
            The pe-2 spacing sits on the span, not the icon — padding on the
            icon itself would offset the fa-spin rotation center.
        --}}
        <span class="pe-2" x-show="!downloading"><i class="fa-solid fa-fw fa-download"></i></span>
        <span class="pe-2" x-show="downloading" style="display: none"><i class="fa-solid fa-fw fa-spinner fa-spin"></i></span>
        @lang('model-browser::global.download-csv.label')
    </button>
</div>
