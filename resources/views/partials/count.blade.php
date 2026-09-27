{{--
    Total result count, rendered inline in the pagination line. It sits in the "count"
    island so it loads and refreshes independently of the (potentially expensive) data
    query.

    The island is `always` rendered, so a filter change that discards the count shows
    the placeholder straight away instead of the stale total. A page change keeps the
    total: the component restores it on hydrate.

    Whenever the count is missing, the placeholder loads it (scoped to the island, so
    the data query in the rows() computed is never re-run). Its key changes on every
    render, so the island morph always puts a fresh element in and its x-init runs
    again, even when the placeholder was already there.
--}}
<span class="model-browser__count">
    @if ($totalCount === null)
        <span
            wire:key="model-browser-count-pending-{{ Str::random(8) }}"
            x-init="$wire.$island('count').loadTotalCount()"
        >
            <span aria-hidden="true">…</span>
            <span class="visually-hidden">@lang('model-browser::pagination.many')</span>
        </span>
    @else
        {{ $totalCount }}
    @endif

    @if ($refreshInterval)
        <span wire:poll.{{ $refreshInterval }}s="loadTotalCount" style="display: none;"></span>
    @endif
</span>
