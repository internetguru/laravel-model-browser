{{--
    Downloads the CSV through the dedicated streaming endpoint instead of a
    Livewire action: the component's signed snapshot is POSTed as a plain form
    submission, so the browser handles the response as a native (streamed)
    file download. A client-generated token is echoed back as a cookie by the
    server, which is how the spinner knows the download has started.
--}}
<div
    class="d-flex justify-content-center"
    x-data="{
        downloading: false,
        pollTimer: null,
        currentToken: null,
        download() {
            if (this.downloading) return;
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
    <button
        class="btn btn-icon btn-white btn-shadow"
        x-on:click="download()"
        x-bind:disabled="downloading"
    >
        <i class="fa-solid fa-fw pe-2" x-bind:class="downloading ? 'fa-spinner fa-spin' : 'fa-download'"></i>
        @lang('model-browser::global.download-csv.label')
    </button>
</div>
