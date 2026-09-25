<?php

namespace Internetguru\ModelBrowser\Components;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Number;
use InternetGuru\LaravelCommon\Support\Sanitizer;
use Internetguru\ModelBrowser\Traits\HasSearchFilters;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BaseModelBrowser extends Component
{
    use HasSearchFilters;

    public const PER_PAGE_MIN = 3;

    public const PER_PAGE_MAX = 150;

    public const PER_PAGE_DEFAULT = 20;

    public const PER_PAGE_OPTIONS = [20, 50, 100];

    /**
     * How many further rows the "load more" button adds to the current window.
     */
    public const PER_PAGE_STEP = 20;

    /**
     * The filter value standing for "this filter has no value at all", written
     * as `attribute:""` in the search query — e.g. `ordered_by:""` lists the
     * orders nobody is named on.
     */
    public const FILTER_EMPTY = '""';

    // Search query security limits — override trait constants
    public const SEARCH_MAX_LENGTH = 500;

    public const SEARCH_MAX_TERMS = 20;

    // Filter types
    public const FILTER_STRING = 'string';

    public const FILTER_NUMBER = 'number';

    public const FILTER_DATE = 'date';

    public const FILTER_OPTIONS = 'options';

    public const FILTER_CHECKBOX = 'checkbox';

    /**
     * Filter types whose value is a range: `1000..2000`, `..1000`, `1000..`, or a
     * single `1000`, which is the range `1000..1000`.
     */
    public const RANGE_TYPES = [self::FILTER_NUMBER, self::FILTER_DATE];

    public const RANGE_SEPARATOR = '..';

    /**
     * The statistics a column's menu offers, in the order they are listed.
     *
     * The `nz` ("non-zero") variants leave out the rows whose value is zero,
     * null or empty — COUNTNZ is the count of the rows that carry a value at
     * all, and is the one also shown in the column header on its own.
     */
    public const STATS = ['sum', 'avg', 'min', 'max', 'count', 'avgnz', 'minnz', 'countnz'];

    /**
     * The statistics that are row counts: plain integers, never run through
     * the column's `formats` callback.
     */
    public const STATS_COUNTS = ['count', 'countnz'];

    #[Locked]
    public string $model;

    #[Locked]
    public string $modelMethod = '';

    #[Locked]
    public array $viewAttributes;

    #[Locked]
    public array $alignments;

    #[Locked]
    public array $formats;

    /**
     * Optional raw-value formatters, keyed by attribute, for attributes
     * whose display value (via `formats`) isn't suitable as-is for the
     * `data-raw` HTML attribute or CSV export. Each function receives
     * ($value, $item) and returns a plain value.
     *
     * By default (no entry needed here), the `data-raw` attribute and CSV
     * export cell use the underlying attribute value; empty only when that
     * value itself is empty/null.
     */
    #[Locked]
    public array $rawFormats = [];

    /**
     * Attributes included in the CSV export, mapped to their labels, hidden
     * in the table. They follow the `viewAttributes` columns, in the order
     * given here. An entry whose key is also a view attribute moves that
     * column into this block instead of duplicating it, so a hidden column
     * can be exported next to the visible one it belongs with.
     *
     * Only attributes with a single value per row belong here: own columns,
     * or dot paths over to-one relations (`customer.email`). A to-many
     * relation has no single value to put in a cell.
     */
    #[Locked]
    public array $exportAttributes = [];

    #[Locked]
    public bool $enableSort = true;

    #[Locked]
    public string $defaultSortColumn = '';

    #[Locked]
    public string $defaultSortDirection = 'asc';

    /**
     * Relations to eager-load on every query (table render + CSV export).
     * Accepts the same shape as Eloquent's `with()` — strings or
     * ['relation' => fn(Builder $q) => ...].
     */
    #[Locked]
    public array $with = [];

    /**
     * Filter configuration.
     * Format: ['attribute' => ['type' => '...', 'label' => '...', ...]]
     * The attribute (filter name) is kebab case, e.g. 'created-by'.
     *
     * Keys:
     * - type: Filter type (string, number, date, options, checkbox); number and date take a range, see RANGE_TYPES
     * - label: Display label
     * - column: Database column name (defaults to the attribute key)
     * - columns: OR group — a list of columns matched with OR instead of a single 'column'.
     *   Each entry is either a column name or an array overriding column/relation/preprocessor/ascii_fast/type
     *   for that column only (unspecified keys fall back to the filter's own config).
     * - relation: Eloquent relation name — wraps the filter in whereHas()
     * - options: Array of options for 'options' type
     * - restrict: (bool) For 'options' type — restrict values to options list (default: false). When false, allows any string and uses LIKE matching.
     * - rules: Optional Laravel validation rules (overrides default type-based rules)
     * - url: Optional URL query parameter name to initialize filter from (takes priority over session)
     * - timezone: Timezone for date filters — parsed date is shifted via Carbon::shiftTimezone($tz)
     * - ascii_fast: (bool) Column stores only ASCII (e-mail, login, slug, …). On SQLite, skips the unaccent() PHP UDF in LIKE matching for a large speedup on big tables.
     *
     * When 'column' or 'columns' is set, filters are auto-applied to the query.
     * When both are omitted, the filter is NOT auto-applied (use HasModelBrowserFilters trait for manual access).
     */
    #[Locked]
    public array $filterConfig = [];

    #[Locked]
    public array $perPageOptions = self::PER_PAGE_OPTIONS;

    /**
     * Auto-refresh interval in seconds. 0 = disabled.
     */
    #[Locked]
    public int $refreshInterval = 0;

    /**
     * Maximum number of rows a CSV export may contain. 0 = unlimited.
     * Defaults to the model-browser.export_limit config value.
     */
    #[Locked]
    public int $exportLimit;

    /**
     * Attributes that are summarized: their header offers the statistics menu,
     * and shows a plain COUNTNZ of its own when some of its rows are empty.
     *
     * Nothing else is summarized, so a browser naming none of them never runs
     * the extra query.
     */
    #[Locked]
    public array $statsAttributes = [];

    /**
     * Largest result count the statistics are computed for. 0 = unlimited.
     *
     * Summarizing walks the whole filtered result set, so above this many
     * rows nothing is computed and the menu asks for narrower filters.
     * Defaults to the model-browser.stats_limit config value.
     */
    #[Locked]
    public int $statsLimit;

    /**
     * Per-column statistics, keyed by attribute. null until loaded (or when
     * the result set is too large — see $statsOverLimit).
     *
     * @var array<string, array{count: int, countnz: int, numeric: bool, sum: ?float, avg: ?float, avgnz: ?float, min: ?float, minnz: ?float, max: ?float}>|null
     */
    public ?array $stats = null;

    /**
     * Whether the result set is larger than `statsLimit`, so no statistics
     * were computed for it.
     */
    public bool $statsOverLimit = false;

    /**
     * Rows per page — also the step the previous/next buttons move by.
     */
    public int $perPage = self::PER_PAGE_DEFAULT;

    /**
     * Rows the "load more" button has appended to the current page, on top of `perPage`.
     *
     * Paging is deliberately unaffected by this: the previous/next buttons always move by
     * `perPage` and drop the extra rows, so a page is always the same size however much
     * was loaded into the one before it.
     */
    public int $extraRows = 0;

    #[Url(as: 'skip', except: 0)]
    public int $skip = 0;

    // #[Url(except: '', as: 'sort-column')]
    public string $sortColumn = '';

    // #[Url(except: '', as: 'sort-direction')]
    public string $sortDirection = 'asc';

    /**
     * Search query string with Gmail-style syntax (e.g. "name:John role:admin").
     *
     * Mirrored into the `q` query parameter, so the active filter is part of the
     * URL (shareable, bookmarkable) and every change pushes a history entry —
     * the browser's back/forward buttons then move between filter states.
     */
    #[Url(as: 'q', except: '', history: true)]
    public string $searchQuery = '';

    /**
     * Total result count (loaded asynchronously).
     * null = not yet loaded (shows placeholder).
     */
    public ?int $totalCount = null;

    /**
     * Current filter values.
     * Format: ['attribute' => 'value']
     */
    public array $filterValues = [];

    /**
     * Session key for storing filters.
     */
    #[Locked]
    public string $filterSessionKey = '';

    /**
     * Whether the count and stats islands have already been told to reload in
     * this request.
     */
    protected bool $summaryRefreshRequested = false;

    public function mount(
        string $model,
        array $viewAttributes = [],
        array $exportAttributes = [],
        array $formats = [],
        array $rawFormats = [],
        array $alignments = [],
        string $defaultSortColumn = '',
        string $defaultSortDirection = 'asc',
        bool $enableSort = true,
        array $filters = [],
        string $filterSessionKey = '',
        int $refreshInterval = 0,
        array $with = [],
        ?int $exportLimit = null,
        array $statsAttributes = [],
        ?int $statsLimit = null,
    ) {
        // if model contains @, split it into model and method
        if (str_contains($model, '@')) {
            [$model, $modelMethod] = explode('@', $model);
            $this->modelMethod = $modelMethod;
            $this->model = $model;
        }
        // Defaults to the first model's fillable attributes
        $this->viewAttributes = $viewAttributes;
        if (! $viewAttributes) {
            $defaultFillables = (new $model)->getFillable();
            $this->viewAttributes = array_combine($defaultFillables, $defaultFillables);
        }
        $this->exportAttributes = $exportAttributes;
        $this->formats = $formats;
        $this->rawFormats = $rawFormats;
        $this->alignments = $alignments;
        $this->enableSort = $enableSort;
        $this->defaultSortColumn = $defaultSortColumn;
        $this->defaultSortDirection = $defaultSortDirection;
        $this->filterConfig = $filters;
        $this->refreshInterval = $refreshInterval;
        $this->with = $with;
        $this->exportLimit = $exportLimit ?? (int) config('model-browser.export_limit');
        $this->statsAttributes = array_values(array_intersect($statsAttributes, array_keys($this->viewAttributes)));
        $this->statsLimit = $statsLimit ?? (int) config('model-browser.stats_limit');
        if (! empty($filters) && ! $filterSessionKey) {
            throw new Exception('Provide filterSessionKey when using filters configuration.');
        }
        $this->initializeFilters();
        $this->updatedPerPage();
        $this->updatedSortColumn();
        $this->updatedSortDirection();
    }

    /**
     * Initialize filter values from URL, session, or defaults.
     * Priority: per-filter `url` query parameters > the `q` search query parameter > session > empty
     * When any per-filter URL parameter is present, all other filters are cleared.
     */
    protected function initializeFilters(): void
    {
        // The #[Url] attribute on $searchQuery runs before mount(), so the `q`
        // query parameter (if any) is already reflected in the property here.
        $urlQuery = $this->sanitizeSearchQuery($this->searchQuery);

        // Load from session
        $sessionFilters = session($this->filterSessionKey, []);
        $sessionQuery = session($this->filterSessionKey . '.query', '');
        $urlParamsToClear = [];

        // Check if any URL filter params are present
        $hasUrlFilters = false;
        foreach ($this->filterConfig as $attribute => $config) {
            if (isset($config['url'])) {
                $urlValue = request()->query($config['url']);
                if ($urlValue !== null && $urlValue !== '') {
                    $hasUrlFilters = true;
                    break;
                }
            }
        }

        // When `q` is present it fully describes the filter state — session values
        // must not leak into it, otherwise back/forward would resurrect them.
        $queryFilters = [];
        if (! $hasUrlFilters && $urlQuery !== '') {
            foreach ($this->parseSearchTerms($urlQuery) as $term) {
                if ($term['key'] !== null) {
                    $queryFilters[$term['key']] = $term['value'];
                }
            }
        }

        foreach ($this->filterConfig as $attribute => $config) {
            $value = '';

            // Check URL parameter first (highest priority)
            if (isset($config['url'])) {
                $urlValue = request()->query($config['url']);
                if ($urlValue !== null && $urlValue !== '') {
                    $value = $urlValue;
                    $urlParamsToClear[] = $config['url'];
                }
            }

            // Fall back to the `q` parameter, then to the session
            if ($value === '' && ! $hasUrlFilters) {
                $value = $urlQuery !== ''
                    ? ($queryFilters[$attribute] ?? '')
                    : ($sessionFilters[$attribute] ?? '');
            }

            $result = $this->validateFilterValue($attribute, $value);
            $this->filterValues[$attribute] = $result['value'];
            // Don't show errors on initial load
        }

        // Build or restore search query
        if ($hasUrlFilters) {
            $this->searchQuery = $this->buildSearchQuery();
        } elseif ($urlQuery !== '') {
            // Keep the query verbatim so free-text terms survive a page reload
            $this->searchQuery = $urlQuery;
        } elseif ($sessionQuery) {
            $this->searchQuery = $sessionQuery;
        } else {
            $this->searchQuery = $this->buildSearchQuery();
        }

        // Save to session (in case URL params were used)
        $this->saveFiltersToSession();

        // Clear URL params that were used to initialize filters
        if (! empty($urlParamsToClear)) {
            $this->dispatch('mb-clear-url-params', params: $urlParamsToClear);
        }
    }

    /**
     * Validate filter value using Laravel validation.
     * Returns array with 'value' and optionally 'error' keys.
     */
    protected function validateFilterValue(string $attribute, mixed $value): array
    {
        if ($value === '' || $value === null) {
            return ['value' => '', 'error' => null];
        }

        $config = $this->filterConfig[$attribute] ?? [];
        $rules = $this->getFilterRules($attribute, $config);

        $this->declareSanitizeType($attribute, $config);

        // A range is valid when each of its bounds is; one without any bound is checked as a whole and fails
        $bounds = in_array($config['type'] ?? self::FILTER_STRING, self::RANGE_TYPES, true)
            ? array_filter(self::splitRange((string) $value), fn (string $bound) => $bound !== '')
            : [$value];

        foreach ($bounds ?: [$value] as $bound) {
            $validator = Validator::make(
                [$attribute => $bound],
                [$attribute => $rules]
            );

            if ($validator->fails()) {
                return [
                    'value' => (string) $value,
                    'error' => $validator->errors()->first($attribute),
                ];
            }
        }

        return ['value' => (string) $value, 'error' => null];
    }

    /**
     * Split a number or date filter value into its lower and upper bound, '' for an open one.
     * A value without the separator is both bounds at once.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitRange(string $value): array
    {
        if (! str_contains($value, self::RANGE_SEPARATOR)) {
            return [trim($value), trim($value)];
        }

        [$from, $to] = explode(self::RANGE_SEPARATOR, $value, 2);

        return [trim($from), trim($to)];
    }

    /**
     * Tell the sanitizer what a filter column holds.
     *
     * A filter is keyed by an application-defined column name, so neither the
     * name nor the generated rules ('nullable|string|max:255' for most of them)
     * say what the value is - but the filter's configured type does. Declaring
     * it here covers both the validator below and the component's own
     * filterValues property for the rest of the request.
     *
     * @param  array<string, mixed>  $config
     */
    protected function declareSanitizeType(string $attribute, array $config): void
    {
        if (! class_exists(Sanitizer::class)) {
            return;
        }

        $pipeline = match ($config['type'] ?? self::FILTER_STRING) {
            self::FILTER_NUMBER => 'number',
            self::FILTER_DATE => 'datetime',
            self::FILTER_CHECKBOX => 'flag',
            default => 'search',
        };

        app(Sanitizer::class)->declareTypes([
            $attribute => $pipeline,
            "filterValues.{$attribute}" => $pipeline,
        ]);
    }

    /**
     * Get validation rules for a filter.
     * Uses custom rules from config if provided, otherwise generates default rules based on type.
     */
    protected function getFilterRules(string $attribute, array $config): string|array
    {
        // Use custom rules if provided
        if (isset($config['rules'])) {
            return $config['rules'];
        }

        // Generate default rules based on type
        $type = $config['type'] ?? self::FILTER_STRING;

        return match ($type) {
            self::FILTER_NUMBER => 'nullable|numeric',
            self::FILTER_DATE => [
                'nullable',
                'string',
                'max:100',
                'regex:/^(?!.*\.\.)[a-z0-9 .:\/+\-]+$/iu',
                function (string $attribute, mixed $value, Closure $fail) use ($config) {
                    try {
                        self::parseDatePeriod((string) $value, $config['timezone'] ?? null);
                    } catch (Exception) {
                        $fail(__('model-browser::global.filters.invalid-date'));
                    }
                },
            ],
            self::FILTER_OPTIONS => ! empty($config['restrict'])
                ? $this->getOptionsRule($config['options'] ?? [])
                : 'nullable|string|max:255',
            self::FILTER_CHECKBOX => 'nullable|boolean',
            default => 'nullable|string|max:255',
        };
    }

    /**
     * Generate validation rule for options filter.
     */
    protected function getOptionsRule(array $options): string
    {
        $validValues = [];
        $isList = array_is_list($options);
        foreach ($options as $optionKey => $optionValue) {
            if (is_array($optionValue) && isset($optionValue['id'])) {
                // Format: [['id' => 'value', 'name' => 'label'], ...]
                $validValues[] = $optionValue['id'];
            } elseif ($isList) {
                // Format: ['value1', 'value2', ...]
                $validValues[] = $optionValue;
            } else {
                // Format: ['value' => 'label', ...] (including numeric keys like IDs)
                $validValues[] = $optionKey;
            }
        }

        return 'nullable|in:' . implode(',', $validValues);
    }

    /**
     * Save filters to session (only valid values + search query).
     */
    protected function saveFiltersToSession(): void
    {
        $validFilters = [];
        foreach ($this->filterValues as $key => $value) {
            if (! $this->getErrorBag()->has('filter-' . $key) && $value !== '' && $value !== null) {
                $validFilters[$key] = $value;
            }
        }
        session([
            $this->filterSessionKey => $validFilters,
            $this->filterSessionKey . '.query' => $this->searchQuery,
        ]);
    }

    /**
     * Get active (non-empty, valid) filters.
     */
    public function getActiveFilters(): array
    {
        return array_filter(
            $this->filterValues,
            fn ($value, $key) => $value !== '' && $value !== null && ! $this->getErrorBag()->has('filter-' . $key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Check if any filter is active.
     */
    public function hasActiveFilters(): bool
    {
        return ! empty($this->getActiveFilters());
    }

    /**
     * Re-apply the search query whenever the property itself changes.
     *
     * Besides plain edits this also covers the browser's back/forward buttons:
     * Livewire restores the pushed `q` value by setting the property directly,
     * so the filter panel, session and pagination have to follow.
     */
    public function updatedSearchQuery(): void
    {
        $this->applySearch();
    }

    /**
     * Hook called after filters/search are changed.
     */
    protected function onFiltersChanged(): void
    {
        $this->resetErrorBag();
        $this->saveFiltersToSession();
        $this->resetPage();
        $this->requestSummaryRefresh();
    }

    /**
     * Discard the count and the statistics, and reload the two islands that
     * carry them (the data query re-runs in this same request via the rows()
     * computed, so neither summary may re-run with it).
     *
     * A single request can change the filters more than once — e.g. the deferred
     * `searchQuery` update and the `applySearch` call that follows it — so the
     * events are dispatched at most once to avoid duplicate island round-trips.
     */
    protected function requestSummaryRefresh(): void
    {
        $this->totalCount = null;
        $this->stats = null;
        $this->statsOverLimit = false;

        if ($this->summaryRefreshRequested) {
            return;
        }

        $this->summaryRefreshRequested = true;
        $this->dispatch('mb-refresh-count');
        $this->dispatch('mb-refresh-stats');
    }

    /**
     * Clear a specific filter.
     */
    public function clearFilter(string $attribute): void
    {
        if (isset($this->filterValues[$attribute])) {
            $this->filterValues[$attribute] = '';
            $this->resetErrorBag('filter-' . $attribute);
            $terms = $this->parseSearchTerms($this->searchQuery);
            $filtered = array_filter($terms, fn ($t) => $t['key'] !== $attribute);
            $this->searchQuery = $this->buildSearchQueryFromTerms($filtered);
            $this->saveFiltersToSession();
            $this->resetPage();
            $this->requestSummaryRefresh();
        }
    }

    /**
     * Apply filters — validate, merge with existing free text, save.
     */
    public function applyFilters(): void
    {
        $this->resetErrorBag();
        $hasErrors = false;

        foreach ($this->filterConfig as $attribute => $config) {
            $value = $this->filterValues[$attribute] ?? '';
            $result = $this->validateFilterValue($attribute, $value);

            $this->filterValues[$attribute] = $result['value'];

            if ($result['error']) {
                $this->addError('filter-' . $attribute, $result['error']);
                $hasErrors = true;
            }
        }

        if (! $hasErrors) {
            // Preserve existing free text terms from search query
            $freeText = array_filter(
                $this->parseSearchTerms($this->searchQuery),
                fn ($t) => $t['key'] === null
            );

            $parts = [];
            foreach ($this->filterValues as $attr => $value) {
                if ($value !== '' && $value !== null) {
                    $parts[] = str_contains($value, ' ') ? "{$attr}:\"{$value}\"" : "{$attr}:{$value}";
                }
            }
            foreach ($freeText as $term) {
                $parts[] = $term['value'];
            }

            $this->searchQuery = implode(' ', $parts);
            $this->saveFiltersToSession();
            $this->resetPage();
            $this->requestSummaryRefresh();
        }
    }

    /**
     * Reload filters from session — used during poll to ensure the table
     * reflects stored (saved) filters, not unsaved UI edits.
     */
    /**
     * The search query used to build the data/count queries.
     *
     * For auto-refreshing tables, the query is built from the stored (saved)
     * filters in the session rather than the live UI properties, so polling
     * never reflects the user's unsaved, in-progress edits.
     */
    protected function effectiveSearchQuery(): string
    {
        if ($this->refreshInterval > 0 && $this->filterSessionKey) {
            return (string) session($this->filterSessionKey . '.query', '');
        }

        return $this->searchQuery;
    }

    /**
     * Load the total result count.
     *
     * Triggered inside the "count" island (see the count partial), so it only
     * re-renders that island — the data query in the rows() computed is never
     * touched.
     */
    public function loadTotalCount(): void
    {
        $query = $this->getQuery();
        $this->applyFiltersToQuery($query, $this->effectiveSearchQuery());
        $this->totalCount = $query->toBase()->getCountForPagination();
    }

    /**
     * Load the per-column statistics.
     *
     * Triggered inside the "stats" island (the table header), so it re-renders
     * that island alone — the data query in the rows() computed is untouched.
     *
     * Only the `statsAttributes` columns are summarized, and a browser that
     * names none never runs the query at all.
     */
    public function loadTotalStats(): void
    {
        $this->stats = null;
        $this->statsOverLimit = false;

        if (empty($this->statsAttributes)) {
            return;
        }

        // The same rows a CSV export would contain — sorting is beside the point
        // for a summary, but it keeps a grouped or aggregated query ordered by a
        // column it actually selects.
        $query = $this->buildFilteredSortedQuery();

        if ($this->statsLimit > 0 && $query->clone()->toBase()->getCountForPagination() > $this->statsLimit) {
            $this->statsOverLimit = true;

            return;
        }

        $this->stats = $this->summarize($query);
    }

    /**
     * Walk the whole result set once and summarize every `statsAttributes` column.
     *
     * Values are read straight off the model (`formats` and `rawFormats` are
     * display concerns and are not applied), so the numbers are in the
     * attribute's own unit. A column counts as numeric only when every value
     * it does have is a number — otherwise just its two row counts are of any
     * use, and the rest stay null.
     *
     * @return array<string, array{count: int, countnz: int, numeric: bool, sum: ?float, avg: ?float, avgnz: ?float, min: ?float, minnz: ?float, max: ?float}>
     */
    protected function summarize(Builder $query): array
    {
        $attributes = $this->statsAttributes;
        $totals = array_fill_keys($attributes, [
            'count' => 0,
            'countnz' => 0,
            'filled' => 0,
            'numbers' => 0,
            'sum' => 0.0,
            'min' => null,
            'minnz' => null,
            'max' => null,
        ]);

        // Offset-based chunking (lazy) needs a deterministic order; the query
        // is unsorted here, so fall back to the primary key.
        if (empty($query->getQuery()->orders)) {
            $query->orderBy((new $this->model)->getKeyName());
        }

        $rows = $this->streamRows($query);

        foreach ($rows as $item) {
            foreach ($attributes as $attribute) {
                $value = Arr::get($item, $attribute);
                $totals[$attribute]['count']++;

                if ($value === null || $value === '' || $value === false) {
                    continue;
                }

                $totals[$attribute]['filled']++;

                if (! is_numeric($value)) {
                    $totals[$attribute]['countnz']++;

                    continue;
                }

                $number = (float) $value;
                $totals[$attribute]['numbers']++;
                $totals[$attribute]['sum'] += $number;
                $totals[$attribute]['min'] = min($totals[$attribute]['min'] ?? $number, $number);
                $totals[$attribute]['max'] = max($totals[$attribute]['max'] ?? $number, $number);

                if ($number == 0.0) {
                    continue;
                }

                $totals[$attribute]['countnz']++;
                $totals[$attribute]['minnz'] = min($totals[$attribute]['minnz'] ?? $number, $number);
            }
        }

        $stats = [];
        foreach ($totals as $attribute => $total) {
            $numeric = $total['numbers'] > 0 && $total['numbers'] === $total['filled'];
            $stats[$attribute] = [
                'count' => $total['count'],
                'countnz' => $total['countnz'],
                'numeric' => $numeric,
                'sum' => $numeric ? $total['sum'] : null,
                'avg' => $numeric && $total['count'] ? $total['sum'] / $total['count'] : null,
                'avgnz' => $numeric && $total['countnz'] ? $total['sum'] / $total['countnz'] : null,
                'min' => $numeric ? $total['min'] : null,
                'minnz' => $numeric ? $total['minnz'] : null,
                'max' => $numeric ? $total['max'] : null,
            ];
        }

        return $stats;
    }

    /**
     * Stream the models of a query. cursor() runs a single query but ignores
     * eager loading, so a query that eager loads relations — through `with` or
     * in the model's own summary method — is walked in chunks instead, or every
     * row would load its relations one by one.
     *
     * @return LazyCollection<int, Model>
     */
    protected function streamRows(Builder $query): LazyCollection
    {
        return empty($query->getEagerLoads()) ? $query->cursor() : $query->lazy(500);
    }

    /**
     * The statistics of one column, or null while none are loaded.
     *
     * @return array{count: int, countnz: int, numeric: bool, sum: ?float, avg: ?float, avgnz: ?float, min: ?float, minnz: ?float, max: ?float}|null
     */
    public function columnStats(string $attribute): ?array
    {
        return $this->stats[$attribute] ?? null;
    }

    /**
     * The statistics of one column, ready for its menu: each one's name, the
     * value as it is shown, and the plain number behind it for the clipboard.
     * The statistics a column has nothing to say about are left out.
     *
     * @return array<int, array{key: string, label: string, display: string, raw: string}>
     */
    public function columnStatsRows(string $attribute): array
    {
        $stats = $this->columnStats($attribute);

        if ($stats === null) {
            return [];
        }

        $rows = [];
        foreach (self::STATS as $key) {
            $value = $stats[$key] ?? null;
            if ($value === null) {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'label' => strtoupper($key),
                'display' => $this->statDisplay($attribute, $key, $value),
                'raw' => (string) (in_array($key, self::STATS_COUNTS, true) ? $value : round((float) $value, 4)),
            ];
        }

        return $rows;
    }

    /**
     * One statistic rendered for display.
     *
     * Row counts are plain integers. The rest are in the column's own unit, so
     * they go through its `formats` callback when it has one — a formatter
     * that needs the row the value came from cannot render an aggregate, and
     * falls back to a plain number.
     */
    public function statDisplay(string $attribute, string $stat, int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (in_array($stat, self::STATS_COUNTS, true)) {
            return (string) $value;
        }

        $format = $this->formats[$attribute] ?? null;
        if ($format) {
            try {
                return (string) $format($value, null);
            } catch (\Throwable) {
                // Fall through to the plain number below.
            }
        }

        return Number::format($value, maxPrecision: 2) ?? (string) $value;
    }

    public function paginationView(): string
    {
        return 'model-browser::empty';
    }

    public function paginationSimpleView(): string
    {
        return 'model-browser::empty';
    }

    public function previousPage(): void
    {
        $this->extraRows = 0;
        $this->skip = max(0, $this->skip - $this->perPage);
    }

    public function nextPage(): void
    {
        $this->extraRows = 0;
        $this->skip += $this->perPage;
    }

    public function resetPage(): void
    {
        $this->skip = 0;
        $this->extraRows = 0;
    }

    public function updatedSortColumn()
    {
        $validAttributes = array_keys($this->viewAttributes);
        if (! in_array($this->sortColumn, $validAttributes)) {
            $this->sortColumn = '';
        }
        $this->resetPage();
    }

    public function updatedSortDirection()
    {
        if (! in_array($this->sortDirection, ['asc', 'desc'])) {
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->perPage = max(self::PER_PAGE_MIN, min(self::PER_PAGE_MAX, $this->perPage));
        $this->resetPage();
    }

    /**
     * Show another `PER_PAGE_STEP` rows below the ones already on the page.
     *
     * Only this page grows: `perPage` is untouched, so the previous/next buttons keep
     * moving by a default page and the rows loaded here are dropped on the way. Nothing
     * about it is remembered for the next visit.
     */
    public function loadMore(): void
    {
        $this->extraRows = max(0, min(
            self::PER_PAGE_MAX - $this->perPage,
            $this->extraRows + self::PER_PAGE_STEP,
        ));
    }

    /**
     * How many rows the current page shows: a full page plus whatever "load more" added.
     */
    public function windowSize(): int
    {
        return $this->perPage + $this->extraRows;
    }

    /**
     * Change the page size, and with it the step the previous/next buttons move by.
     *
     * The shipped views offer no control for this — the page holds `PER_PAGE_DEFAULT`
     * rows and "load more" grows it — so this is here for a host rendering its own,
     * and it lasts for the current visit only.
     */
    public function setPerPage(int $value): void
    {
        $this->perPage = $value;
        $this->updatedPerPage();
    }

    /**
     * The paginated rows for the current page.
     *
     * Exposed as a computed property so the (potentially expensive) data query
     * is only executed when the "results" island actually renders. When an
     * island-scoped action runs (e.g. loadTotalCount in the "count" island),
     * the results island is skipped and this query never runs.
     */
    #[Computed]
    public function rows(): Paginator
    {
        return $this->getData();
    }

    public function render()
    {
        return view('model-browser::livewire.base');
    }

    public function getAlignment(string $attribute, mixed $value): string
    {
        return $this->alignments[$attribute] ?? (is_numeric($value) ? 'end' : 'start');
    }

    #[Renderless]
    public function downloadCsv(bool $truncate = false): StreamedResponse
    {
        $exportName = $this->generateExportFilename();
        $columns = $this->exportColumns();
        $headers = array_values($columns);
        $attributes = array_keys($columns);
        $query = $this->buildFilteredSortedQuery();

        // The export button asks the user to confirm truncating the export
        // when over the limit; the download endpoint is directly POSTable,
        // so refuse outright unless the client already confirmed truncation.
        if ($this->exportLimit > 0 && $query->clone()->toBase()->getCountForPagination() > $this->exportLimit) {
            if (! $truncate) {
                abort(413, trans('model-browser::global.download-csv.limit-exceeded', ['limit' => $this->exportLimit]));
            }
        }

        // Offset-based chunking (lazy) needs a deterministic order; fall back
        // to the primary key when no sort column is active.
        $hasOrder = ! empty($query->getQuery()->orders);
        if (! $hasOrder) {
            $query->orderBy((new $this->model)->getKeyName());
        }

        if ($this->exportLimit > 0) {
            $query->limit($this->exportLimit);
        }

        return response()->streamDownload(function () use ($headers, $query, $attributes) {
            // Large exports can easily exceed max_execution_time, and since
            // headers are already sent, the resulting error dump would end up
            // inside the downloaded CSV.
            @set_time_limit(0);

            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            $rows = $this->streamRows($query);

            $count = 0;
            foreach ($rows as $item) {
                $this->applyRawFormats($item);
                $row = [];
                foreach ($attributes as $attribute) {
                    $row[] = $this->itemValueRaw($item, $attribute);
                }
                fputcsv($out, $row);
                if (++$count % 500 === 0) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
            fclose($out);
        }, $exportName, ['Content-Type' => 'text/csv']);
    }

    /**
     * The columns a CSV export contains, mapped to their labels: the visible
     * columns, then the `exportAttributes` ones. A view attribute repeated in
     * `exportAttributes` is exported once, in its `exportAttributes` position.
     *
     * @return array<string, string>
     */
    public function exportColumns(): array
    {
        return array_merge(
            array_diff_key($this->viewAttributes, $this->exportAttributes),
            $this->exportAttributes,
        );
    }

    protected function generateExportFilename(): string
    {
        $modelName = class_basename($this->model);
        $fileName = $modelName;

        $sortColumn = $this->getActiveSortColumn();
        $sortDirection = $this->getActiveSortDirection();
        if ($sortColumn) {
            $fileName .= "-sort-{$sortColumn}-{$sortDirection}";
        }

        $fileName .= '-' . date('Y-m-d');
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileName);

        return "{$fileName}.csv";
    }

    /**
     * Get the base query builder.
     */
    protected function getQuery(): Builder
    {
        $query = $this->modelMethod
            ? $this->model::{$this->modelMethod}()
            : $this->model::query();

        if (! empty($this->with)) {
            $query->with($this->with);
        }

        return $query;
    }

    /**
     * Auto-apply search terms to the query.
     * Parses searchQuery into terms. All terms are AND'd together.
     * Free text (no key:) searches all string-type filter columns (OR within one term).
     * A keyed term targets the filter's 'column', or OR's across its 'columns' group.
     */
    protected function applyFiltersToQuery(Builder $query, ?string $searchQuery = null): void
    {
        $terms = $this->parseSearchTerms($searchQuery ?? $this->searchQuery);

        if (empty($terms)) {
            return;
        }

        // Collect searchable columns for free text search (only filters with explicit 'column'/'columns')
        $searchableColumns = [];
        foreach ($this->filterConfig as $attr => $config) {
            $type = $config['type'] ?? self::FILTER_STRING;
            if (! in_array($type, [self::FILTER_STRING, self::FILTER_OPTIONS])) {
                continue;
            }
            foreach ($this->getFilterColumns($config) as $col) {
                $searchableColumns[] = $col;
            }
        }

        // All terms are AND'd together
        foreach ($terms as $term) {
            if ($term['key'] === null) {
                // Free text → OR across searchable columns (match any), AND'd with other terms
                if (empty($searchableColumns)) {
                    continue;
                }
                $query->where(function (Builder $sub) use ($term, $searchableColumns) {
                    foreach ($searchableColumns as $col) {
                        $sub->orWhere(function (Builder $q) use ($col, $term) {
                            $value = $col['preprocessor'] ? ($col['preprocessor'])($term['value']) : $term['value'];
                            $this->applyCondition($q, $col['column'], $col['relation'], self::FILTER_STRING, $value, null, $col['ascii_fast']);
                        });
                    }
                });
            } else {
                // Specific filter — skip filters without explicit 'column'/'columns'
                $config = $this->filterConfig[$term['key']] ?? [];
                $columns = $this->getFilterColumns($config);
                if (empty($columns)) {
                    continue;
                }
                // `attribute:""` asks for the rows this filter finds nothing on
                if ($term['value'] === self::FILTER_EMPTY) {
                    $query->whereNot(function (Builder $sub) use ($columns) {
                        foreach ($columns as $col) {
                            $sub->orWhere(fn (Builder $q) => $this->applyPresence($q, $col));
                        }
                    });

                    continue;
                }
                // Skip invalid filter values
                $result = $this->validateFilterValue($term['key'], $term['value']);
                if ($result['error']) {
                    continue;
                }
                $applyColumn = function (Builder $q, array $col) use ($config, $term) {
                    $type = $col['type'];
                    if ($type === self::FILTER_OPTIONS && empty($config['restrict'])) {
                        $type = self::FILTER_STRING;
                    }
                    $value = $this->preprocessFilterValue($col, $term['value']);
                    $this->applyCondition($q, $col['column'], $col['relation'], $type, $value, $col['timezone'], $col['ascii_fast']);
                };

                if (count($columns) === 1) {
                    $applyColumn($query, $columns[0]);

                    continue;
                }

                // OR group — the term matches when any of the configured columns matches
                $query->where(function (Builder $sub) use ($columns, $applyColumn) {
                    foreach ($columns as $col) {
                        $sub->orWhere(fn (Builder $q) => $applyColumn($q, $col));
                    }
                });
            }
        }
    }

    /**
     * Run the column's preprocessor on a filter value, on each bound of a range separately.
     *
     * @param  array{preprocessor: ?string, type: string}  $col
     */
    protected function preprocessFilterValue(array $col, string $value): string
    {
        $preprocessor = $col['preprocessor'];
        if (! $preprocessor) {
            return $value;
        }

        if (! in_array($col['type'], self::RANGE_TYPES, true) || ! str_contains($value, self::RANGE_SEPARATOR)) {
            return $preprocessor($value);
        }

        return implode(self::RANGE_SEPARATOR, array_map(
            fn (string $bound) => $bound === '' ? '' : $preprocessor($bound),
            self::splitRange($value)
        ));
    }

    /**
     * Normalize a filter config into the list of columns it targets.
     *
     * A filter either targets a single 'column' or an OR group of 'columns'.
     * Each 'columns' entry is a column name, or an array overriding any of
     * column/relation/preprocessor/ascii_fast/type/timezone for that column
     * only — anything not overridden falls back to the filter's own config.
     *
     * @return array<int, array{column: string, relation: ?string, preprocessor: ?string, ascii_fast: bool, type: string, timezone: ?string}>
     */
    protected function getFilterColumns(array $config): array
    {
        $normalize = function (array $spec) use ($config): array {
            $preprocessor = $spec['preprocessor'] ?? $config['preprocessor'] ?? null;

            return [
                'column' => $spec['column'],
                'relation' => $spec['relation'] ?? $config['relation'] ?? null,
                'preprocessor' => ($preprocessor && \function_exists($preprocessor)) ? $preprocessor : null,
                'ascii_fast' => ! empty($spec['ascii_fast'] ?? $config['ascii_fast'] ?? false),
                'type' => $spec['type'] ?? $config['type'] ?? self::FILTER_STRING,
                'timezone' => $spec['timezone'] ?? $config['timezone'] ?? null,
            ];
        };

        if (! empty($config['columns'])) {
            return array_map(
                fn ($spec) => $normalize(is_array($spec) ? $spec : ['column' => $spec]),
                array_values($config['columns'])
            );
        }

        if (array_key_exists('column', $config)) {
            return [$normalize(['column' => $config['column']])];
        }

        return [];
    }

    /**
     * Match the rows where this column carries a value.
     *
     * Negated by the caller, this is what `attribute:""` searches for: a filter over a
     * relation finds nothing when the relation itself is missing, so the check is nested
     * in `whereHas` exactly like a value match would be.
     *
     * @param  array{column: string, relation: ?string}  $col
     */
    protected function applyPresence(Builder $query, array $col): void
    {
        $present = fn (Builder $q) => $q
            ->whereNotNull($col['column'])
            ->where($col['column'], '!=', '');

        if (! $col['relation']) {
            $present($query);

            return;
        }

        $nested = $present;
        foreach (array_reverse(explode('.', $col['relation'])) as $part) {
            $inner = $nested;
            $nested = fn (Builder $q) => $q->whereHas($part, $inner);
        }
        $nested($query);
    }

    /**
     * Apply a single filter condition with AND semantics.
     */
    protected function applyCondition(Builder $query, string $column, ?string $relation, string $type, string $value, ?string $timezone = null, bool $asciiFast = false): void
    {
        $this->whereInRelation($query, $relation, fn (Builder $q) => $this->applyWhere($q, $column, $type, $value, $timezone, $asciiFast));
    }

    /**
     * Apply the callback to the query, nested in `whereHas` along the relation's dot path.
     */
    protected function whereInRelation(Builder $query, ?string $relation, Closure $apply): void
    {
        if (! $relation) {
            $apply($query);

            return;
        }

        $nested = $apply;
        foreach (array_reverse(explode('.', $relation)) as $part) {
            $inner = $nested;
            $nested = fn (Builder $q) => $q->whereHas($part, $inner);
        }
        $nested($query);
    }

    /**
     * Apply one filter condition on a column of the query's own table.
     */
    protected function applyWhere(Builder $query, string $column, string $type, string $value, ?string $timezone = null, bool $asciiFast = false): void
    {
        try {
            match ($type) {
                self::FILTER_STRING => $query->whereLikeUnaccented($column, $value, $asciiFast),
                self::FILTER_NUMBER => $this->applyRange($query, $column, $value, fn (string $bound) => $bound, fn (string $bound) => $bound),
                self::FILTER_DATE => $this->applyRange(
                    $query,
                    $column,
                    $value,
                    fn (string $bound) => self::parseDatePeriod($bound, $timezone)[0],
                    fn (string $bound) => self::parseDatePeriod($bound, $timezone)[1],
                ),
                self::FILTER_OPTIONS, self::FILTER_CHECKBOX => $query->where($column, $value),
                default => $query->whereLikeUnaccented($column, $value, $asciiFast),
            };
        } catch (Exception $e) {
            // Invalid value (e.g. unparseable date), skip
        }
    }

    /**
     * Apply both bounds of a range value to the same row; an open bound is left out.
     *
     * @param  Closure(string): mixed  $lower  turns the lower bound into the value compared with
     * @param  Closure(string): mixed  $upper  turns the upper bound into the value compared with
     */
    protected function applyRange(Builder $query, string $column, string $value, Closure $lower, Closure $upper): void
    {
        [$from, $to] = self::splitRange($value);
        // Both bounds are converted first, so an unparseable one leaves no half of the range applied
        $from = $from === '' ? null : $lower($from);
        $to = $to === '' ? null : $upper($to);

        if ($from !== null) {
            $query->where($column, '>=', $from);
        }
        if ($to !== null) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * The period a date bound stands for, read in the filter's timezone.
     *
     * `2026` is the whole year, `2026-10` and `10.2026` the whole month, `2026-10-15` the whole day.
     * A relative bound spans the unit it names: `3 days ago` is that day, `last month` that month.
     * A bound with a time is that moment.
     *
     * @return array{0: Carbon, 1: Carbon} the first and the last moment, in the application timezone
     *
     * @throws Exception when the bound is not a date
     */
    public static function parseDatePeriod(string $value, ?string $timezone = null): array
    {
        $appTimezone = config('app.timezone', 'UTC');
        $timezone ??= $appTimezone;
        $value = trim($value);

        if (preg_match('/^\d{4}$/', $value)) {
            [$date, $unit] = [Carbon::create((int) $value, 1, 1, 0, 0, 0, $timezone), 'year'];
        } elseif (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $match) || preg_match('/^(\d{1,2})[.\/](\d{4})$/', $value, $match)) {
            [$year, $month] = str_contains($match[0], '-') ? [$match[1], $match[2]] : [$match[2], $match[1]];
            if ((int) $month < 1 || (int) $month > 12) {
                throw new Exception("Invalid month in '{$value}'.");
            }
            [$date, $unit] = [Carbon::create((int) $year, (int) $month, 1, 0, 0, 0, $timezone), 'month'];
        } else {
            $date = Carbon::parse($value, $timezone);
            $unit = self::relativeDateUnit($value) ?? ($date->format('H:i:s') === '00:00:00' ? 'day' : null);
        }

        if ($unit === null) {
            return [$date->copy()->timezone($appTimezone), $date->copy()->timezone($appTimezone)];
        }

        return [
            $date->copy()->startOf($unit)->timezone($appTimezone),
            $date->copy()->endOf($unit)->timezone($appTimezone),
        ];
    }

    /**
     * The unit a relative date names, e.g. 'day' for `3 days ago` or `yesterday`, 'month' for `last month`.
     */
    protected static function relativeDateUnit(string $value): ?string
    {
        $value = mb_strtolower($value);
        if (preg_match('/\b(year|month|week|day|hour|minute)s?\b/', $value, $match)) {
            return $match[1];
        }

        return preg_match('/\b(today|yesterday|tomorrow)\b/', $value) ? 'day' : null;
    }

    /**
     * Get the active sort column (user selection or default).
     */
    public function getActiveSortColumn(): string
    {
        return $this->sortColumn ?: $this->defaultSortColumn;
    }

    /**
     * Get the active sort direction (user selection or default).
     */
    public function getActiveSortDirection(): string
    {
        return $this->sortColumn ? $this->sortDirection : $this->defaultSortDirection;
    }

    /**
     * Build the filtered + sorted query (no pagination, no execution).
     */
    protected function buildFilteredSortedQuery(): Builder
    {
        $query = $this->getQuery();

        // Auto-apply filters that have 'column' configured
        $this->applyFiltersToQuery($query, $this->effectiveSearchQuery());

        // Apply database-level sorting (single column)
        // User sorting only when enableSort is true, default sort always applies
        $sortColumn = $this->enableSort ? $this->getActiveSortColumn() : $this->defaultSortColumn;
        $sortDirection = $this->enableSort ? $this->getActiveSortDirection() : $this->defaultSortDirection;
        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return $query;
    }

    /**
     * Get data with database-level sorting and pagination.
     *
     * The page starts on a `perPage` boundary but may be taller than one page when
     * "load more" has been used, so the offset cannot be derived from the page size the
     * way `simplePaginate()` does it — the window is taken by hand instead. One row
     * beyond it is fetched so the paginator knows whether anything follows.
     */
    protected function getData(): Paginator
    {
        $query = $this->buildFilteredSortedQuery();

        // Clamp skip to a valid page boundary and derive page number
        $this->skip = max(0, intdiv($this->skip, $this->perPage) * $this->perPage);
        $page = intdiv($this->skip, $this->perPage) + 1;
        $window = $this->windowSize();

        Paginator::defaultSimpleView($this->paginationSimpleView());

        $data = new Paginator(
            $query->skip($this->skip)->take($window + 1)->get(),
            $window,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
        $data->setCollection($this->format($data->getCollection()));

        return $data;
    }

    public function itemValue($item, $attribute)
    {
        $formattedAttribute = "{$attribute}Formatted";
        if (isset($item->{$formattedAttribute})) {
            return $item->{$formattedAttribute};
        }

        return Arr::get($item, $attribute);
    }

    /**
     * The raw (unformatted) value for an attribute. Uses the rawFormats
     * callback when one is configured for it, otherwise falls back to the
     * underlying attribute value. Also the value used for a CSV export cell.
     * Empty/null only when the underlying value itself is empty/null.
     */
    public function itemValueRaw($item, $attribute)
    {
        $rawAttribute = "{$attribute}Raw";
        if (isset($item->{$rawAttribute})) {
            return $item->{$rawAttribute};
        }

        return Arr::get($item, $attribute);
    }

    protected function format(Collection $data): Collection
    {
        return $data->transform(fn ($item) => $this->formatItem($item));
    }

    protected function formatItem($item)
    {
        $this->applyFormats($item);
        $this->applyRawFormats($item);

        return $item;
    }

    protected function applyFormats($item): void
    {
        foreach ($this->formats as $attribute => $format) {
            $value = Arr::get($item, $attribute);
            if ($value === null) {
                continue;
            }
            $item->{$attribute . 'Formatted'} = $format($value, $item);
        }
    }

    protected function applyRawFormats($item): void
    {
        foreach ($this->rawFormats as $attribute => $format) {
            $value = Arr::get($item, $attribute);
            if ($value === null) {
                continue;
            }
            $item->{$attribute . 'Raw'} = $format($value, $item);
        }
    }
}
