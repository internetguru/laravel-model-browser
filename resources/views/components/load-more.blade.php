{{--
    Shows another `BaseModelBrowser::PER_PAGE_STEP` rows below the ones already on the
    page. Only this page grows — the previous/next buttons keep moving by a default page
    and drop the extra rows. Only offered while there is something left to add.
--}}
@if ($data->hasMorePages())
    <div class="model-browser__load-more d-flex justify-content-center my-3 mb-5">
        {{-- A link, not a button: this asks for more of what is already on the page,
             which is not the same kind of act as the controls around it. --}}
        <button
            type="button"
            class="model-browser__load-more-link"
            wire:click="loadMore"
            wire:loading.attr="disabled"
        >@lang('model-browser::pagination.load-more')</button>
    </div>
@endif
