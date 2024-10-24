<div class="d-flex justify-content-center">
    <button
        class="btn btn-primary"
        wire:click="downloadCsv"
        @if ($this->filter != '')
            wire:confirm="{{ __('model-browser::global.download-csv.confirm-filter') }}"
        @endif
    >@lang('model-browser::global.download-csv.label')</button>
</div>
