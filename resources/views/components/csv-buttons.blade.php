<div class="d-flex justify-content-center">
    <button
        class="btn btn-icon btn-secondary"
        wire:click="downloadCsv"
        @if ($this->filter != '')
            wire:confirm="{{ __('model-browser::global.download-csv.confirm-filter') }}"
        @endif
    >
        <i class="fa-solid fa-fw fa-download pe-2"></i>
        @lang('model-browser::global.download-csv.label')
    </button>
</div>
