{{--
    Total result count. Rendered inside the "count" island so it loads and
    refreshes independently of the (potentially expensive) data query.

    The island only re-renders when targeted, so:
    - on first render it self-triggers loadTotalCount (scoped to the island);
    - when filters/search change, the component dispatches `mb-refresh-count`
      and the listener below reloads just this island — the data query in the
      rows() computed is never re-run.
--}}
<div
    class="model-browser__count d-flex justify-content-end align-items-center my-2"
    x-on:mb-refresh-count.window="$wire.$island('count').loadTotalCount()"
>
    @if ($totalCount === null)
        <span x-data x-init="$wire.$island('count').loadTotalCount()" style="display: none;"></span>
        <span class="text-muted">@lang('model-browser::pagination.many')</span>
    @else
        <span>{{ $totalCount }} @lang('model-browser::pagination.results')</span>
    @endif

    @if ($refreshInterval)
        <span wire:poll.{{ $refreshInterval }}s="loadTotalCount" style="display: none;"></span>
    @endif
</div>
