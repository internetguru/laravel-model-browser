<?php

namespace Internetguru\ModelBrowser\Components;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use InternetGuru\LaravelCommon\Contracts\HasLabel;
use InternetGuru\LaravelCommon\Support\Sanitizer;
use Internetguru\ModelBrowser\Traits\HasSearchFilters;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

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
     * as a bare `attribute:` in the search query — e.g. `ordered-by:` lists the
     * orders nobody is named on.
     */
    public const FILTER_EMPTY = '""';

    /**
     * Seconds a loaded total count and statistics are kept for the page
     * changes that follow.
     */
    public const TOTAL_COUNT_TTL = 3600;

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
     * The statistics a column's menu offers, in the groups and order they are
     * listed: the ones every column has, the dates' span, SUM, which zeros
     * never change, then those counting the zeros, each beside its non-zero
     * counterpart (see STATS_NONZERO).
     *
     * Empty values (null or '') are no values at all and are left out of every
     * statistic but EMPTY; a zero or `false` is a value. The `nz` ("non-zero")
     * variants leave out the zeros as well.
     */
    public const STATS_GROUPS = [
        ['distinct', 'empty', 'nonempty'],
        ['earliest', 'latest'],
        ['sum'],
        ['avg', 'median', 'min', 'max', 'count'],
    ];

    /**
     * The non-zero counterpart of each statistic of the paired group, shown
     * in a column of its own.
     */
    public const STATS_NONZERO = [
        'avg' => 'avgnz',
        'median' => 'mediannz',
        'min' => 'minnz',
        'max' => 'maxnz',
        'count' => 'countnz',
    ];

    public const STATS = [...self::STATS_GROUPS[0], ...self::STATS_GROUPS[1], ...self::STATS_GROUPS[2], ...self::STATS_GROUPS[3], ...self::STATS_NONZERO];

    /**
     * The statistics that are row counts: plain integers, never run through
     * the column's `formats` callback.
     */
    public const STATS_COUNTS = ['distinct', 'empty', 'nonempty', 'count', 'countnz'];

    /**
     * The row counts shown with their share of all the rows.
     */
    public const STATS_SHARES = ['empty', 'nonempty'];

    /**
     * The statistics that are moments, kept as timestamps.
     */
    public const STATS_DATES = ['earliest', 'latest'];

    /**
     * The statistics whose value spans the second column too: they have no
     * share or non-zero counterpart, and a date or a total is the longest value.
     */
    public const STATS_WIDE = ['earliest', 'latest', 'sum'];

    /**
     * The indexes in STATS_GROUPS of the numeric statistics, left out of the
     * menu for a column that is not numeric.
     */
    public const STATS_NUMERIC_GROUPS = [2, 3];

    /**
     * The index in STATS_GROUPS of the group shown beside its non-zero
     * counterparts, under a header naming the two columns.
     */
    public const STATS_PAIRED_GROUP = 3;

    /**
     * How many of a column's most frequent values its menu lists.
     */
    public const STATS_VALUES_LIMIT = 10;

    /**
     * The distinct values counted per column at most; a column with more is
     * not listed (only reachable with `statsLimit` off).
     */
    public const STATS_VALUES_MAX = 10000;

    /**
     * The average length of a column's texts above which its values are not
     * listed: notes and descriptions rather than names.
     */
    public const STATS_LONG_TEXT = 50;

    /**
     * A text this long is counted under its hash, so long values cost no memory.
     */
    public const STATS_HASHED_TEXT = 64;

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

    /**
     * The list's name at the start of a CSV export's file name, before the
     * time of the export. Defaults to the model's plural, e.g. `order-items`.
     */
    #[Locked]
    public string $exportName = '';

    /**
     * The list's name as its page calls it, heading each statistics menu
     * before the column's name.
     */
    #[Locked]
    public string $title = '';

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
     * Attributes that are summarized: their header offers the statistics menu.
     * Every view attribute unless the `statsAttributes` prop names some, and
     * none when it is an empty array.
     */
    #[Locked]
    public array $statsAttributes = [];

    /**
     * Summarized attributes whose values are texts even when they look like
     * numbers, such as order numbers: they get no numeric statistics.
     */
    #[Locked]
    public array $statsTextAttributes = [];

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
     * Per-column statistics, keyed by attribute (see summarize()). null until
     * loaded — on the first opening of a menu, or right after the count for a
     * short list — or when the result set is too large (see $statsOverLimit).
     *
     * @var array<string, array<string, mixed>>|null
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
        ?array $statsAttributes = null,
        ?int $statsLimit = null,
        string $exportName = '',
        array $statsTextAttributes = [],
        string $title = '',
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
        $this->exportName = $exportName;
        $this->title = $title;
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
        $this->statsAttributes = $statsAttributes === null
            ? array_keys($this->viewAttributes)
            : array_values(array_intersect($statsAttributes, array_keys($this->viewAttributes)));
        $this->statsTextAttributes = $statsTextAttributes;
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

        // No value at all is a valid search whatever the filter's type
        if ($value === self::FILTER_EMPTY) {
            return ['value' => $value, 'error' => null];
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
                    $parts[] = $this->formatSearchTerm($attr, $value);
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
     * touched. A list short enough to summarize cheaply has its statistics
     * loaded right after.
     */
    public function loadTotalCount(): void
    {
        $searchQuery = $this->effectiveSearchQuery();
        $query = $this->getQuery();
        $this->applyFiltersToQuery($query, $searchQuery);
        $this->totalCount = $query->toBase()->getCountForPagination();

        Cache::put($this->totalCountCacheKey(), [
            'query' => $searchQuery,
            'count' => $this->totalCount,
        ], self::TOTAL_COUNT_TTL);

        if ($this->shouldLoadStatsAutomatically()) {
            $this->dispatch('mb-load-stats');
        }
    }

    /**
     * Whether the statistics are missing for a list no longer than the
     * `model-browser.stats_auto_limit` config value.
     */
    protected function shouldLoadStatsAutomatically(): bool
    {
        $limit = (int) config('model-browser.stats_auto_limit');

        return $limit > 0
            && $this->statsAttributes
            && $this->stats === null
            && ! $this->statsOverLimit
            && $this->totalCount <= $limit;
    }

    /**
     * Restore the count and the statistics the snapshot lost.
     *
     * The count and stats islands load in separate requests, and Livewire keeps
     * the snapshot of whichever answers last, so what one loaded can be nulled
     * by the other. Both are kept server side for the query they were taken
     * for, and a page change never has to load them again.
     */
    public function hydrate(): void
    {
        $searchQuery = $this->effectiveSearchQuery();

        if ($this->totalCount === null) {
            $cached = Cache::get($this->totalCountCacheKey());

            if (is_array($cached) && $cached['query'] === $searchQuery) {
                $this->totalCount = $cached['count'];
            }
        }

        if ($this->stats === null && ! $this->statsOverLimit) {
            $cached = Cache::get($this->statsCacheKey());

            if (is_array($cached) && $cached['query'] === $searchQuery) {
                $this->stats = $cached['stats'];
                $this->statsOverLimit = $cached['overLimit'];
            }
        }
    }

    protected function totalCountCacheKey(): string
    {
        return 'model-browser.total-count.' . $this->getId();
    }

    protected function statsCacheKey(): string
    {
        return 'model-browser.stats.' . $this->getId();
    }

    /**
     * Load the per-column statistics.
     *
     * Triggered inside the "stats" island (the table header), so it re-renders
     * that island alone — the data query in the rows() computed is untouched.
     * Every column is summarized in the one pass: reading the rows is what
     * costs, not counting one more column.
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
        } else {
            $this->stats = $this->summarize($query);
        }

        Cache::put($this->statsCacheKey(), [
            'query' => $this->effectiveSearchQuery(),
            'stats' => $this->stats,
            'overLimit' => $this->statsOverLimit,
        ], self::TOTAL_COUNT_TTL);
    }

    /**
     * Walk the whole result set once and summarize every `statsAttributes` column.
     *
     * Values are read straight off the model, so the numbers are in the
     * attribute's own unit. Empty values are only counted as such: COUNT is
     * the count of the filled ones. A column counts as numeric only when every
     * value it does have is a number, and as a date column when every one is a
     * date — otherwise its numeric statistics or its span stay null.
     *
     * The values themselves are counted too, dates by the day, and the most
     * frequent ones are rendered for the menu (see valueListing()).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function summarize(Builder $query): array
    {
        $attributes = $this->statsAttributes;
        $totals = array_fill_keys($attributes, [
            'count' => 0,
            'countnz' => 0,
            'empty' => 0,
            'numbers' => 0,
            'sum' => 0.0,
            'nonzero' => [],
            'min' => null,
            'minnz' => null,
            'max' => null,
            'maxnz' => null,
            'dates' => 0,
            'earliest' => null,
            'latest' => null,
            'texts' => 0,
            'length' => 0,
            'values' => [],
            'samples' => [],
            'unlisted' => false,
            'overflow' => false,
            'rowKeys' => [],
        ]);

        // Offset-based chunking (lazy) needs a deterministic order; the query
        // is unsorted here, so fall back to the primary key.
        if (empty($query->getQuery()->orders)) {
            $query->orderBy((new $this->model)->getKeyName());
        }

        $texts = array_fill_keys($attributes, false);
        foreach ($this->statsTextAttributes as $attribute) {
            $texts[$attribute] = true;
        }

        foreach ($this->streamRows($query) as $item) {
            $rowKey = $item->getKey();
            foreach ($attributes as $attribute) {
                $this->tally($totals[$attribute], Arr::get($item, $attribute), $texts[$attribute], $rowKey);
            }
        }

        foreach ($totals as &$total) {
            $total['listing'] = $this->listing($total);
            $total['ranked'] = $total['listing'] === 'values' ? $this->rankedValues($total['values']) : [];
        }
        unset($total);
        $rows = $this->listedRows($totals);

        $stats = [];
        foreach ($totals as $attribute => $total) {
            $numeric = $total['numbers'] > 0 && $total['numbers'] === $total['count'];
            $dated = $total['dates'] > 0 && $total['dates'] === $total['count'];
            $stats[$attribute] = [
                'rows' => $total['count'] + $total['empty'],
                'distinct' => $total['unlisted'] || $total['overflow'] ? null : count($total['values']),
                'empty' => $total['empty'],
                'nonempty' => $total['count'],
                'earliest' => $dated ? $total['earliest'] : null,
                'latest' => $dated ? $total['latest'] : null,
                'count' => $total['count'],
                'countnz' => $total['countnz'],
                'numeric' => $numeric,
                'sum' => $numeric ? $total['sum'] : null,
                'avg' => $numeric && $total['count'] ? $total['sum'] / $total['count'] : null,
                'avgnz' => $numeric && $total['countnz'] ? $total['sum'] / $total['countnz'] : null,
                'median' => $numeric ? $this->median($total['nonzero'], $total['count'] - $total['countnz']) : null,
                'mediannz' => $numeric ? $this->median($total['nonzero']) : null,
                'min' => $numeric ? $total['min'] : null,
                'minnz' => $numeric ? $total['minnz'] : null,
                'max' => $numeric ? $total['max'] : null,
                'maxnz' => $numeric ? $total['maxnz'] : null,
                ...$this->valueListing($attribute, $total, $rows),
            ];
        }

        return $stats;
    }

    /**
     * Add one value to the running totals of its column. A column of texts
     * never counts its values as numbers.
     *
     * @param  array<string, mixed>  $total
     */
    protected function tally(array &$total, mixed $value, bool $text = false, int|string|null $rowKey = null): void
    {
        if ($value === null || $value === '') {
            $total['empty']++;

            return;
        }

        $total['count']++;
        $this->tallyValue($total, $value, $rowKey);

        if (is_string($value)) {
            $total['texts']++;
            $total['length'] += mb_strlen($value);
        }

        if ($value instanceof DateTimeInterface) {
            $timestamp = $value->getTimestamp();
            $total['dates']++;
            $total['earliest'] = min($total['earliest'] ?? $timestamp, $timestamp);
            $total['latest'] = max($total['latest'] ?? $timestamp, $timestamp);
        }

        if ($text || ! is_numeric($value)) {
            $total['countnz']++;

            return;
        }

        $number = (float) $value;
        $total['numbers']++;
        $total['sum'] += $number;
        $total['min'] = min($total['min'] ?? $number, $number);
        $total['max'] = max($total['max'] ?? $number, $number);

        if ($number == 0.0) {
            return;
        }

        $total['countnz']++;
        $total['nonzero'][] = $number;
        $total['minnz'] = min($total['minnz'] ?? $number, $number);
        $total['maxnz'] = max($total['maxnz'] ?? $number, $number);
    }

    /**
     * Count one value among the column's distinct values, and remember the
     * key of the first row it came from. A value that cannot be counted, or
     * one too many distinct values, ends the counting for the column.
     *
     * @param  array<string, mixed>  $total
     */
    protected function tallyValue(array &$total, mixed $value, int|string|null $rowKey = null): void
    {
        if ($total['unlisted'] || $total['overflow']) {
            return;
        }

        $counted = $this->countedValue($value);

        if ($counted === null || (! isset($total['values'][$counted[0]]) && count($total['values']) >= self::STATS_VALUES_MAX)) {
            $total[$counted === null ? 'unlisted' : 'overflow'] = true;
            $total['values'] = [];
            $total['samples'] = [];
            $total['rowKeys'] = [];

            return;
        }

        [$key, $sample] = $counted;
        $total['values'][$key] = ($total['values'][$key] ?? 0) + 1;
        $total['samples'][$key] ??= $sample;
        $total['rowKeys'][$key] ??= $rowKey;
    }

    /**
     * The key a value is counted under, and the value shown for it: a date
     * stands for its day in the display timezone, and a long text is counted
     * under its hash. null for a value that cannot be counted, such as an array.
     *
     * @return array{0: string, 1: mixed}|null
     */
    protected function countedValue(mixed $value): ?array
    {
        if ($value instanceof DateTimeInterface) {
            $day = Carbon::instance($value)->toDisplayTimezone()->startOfDay();

            return [$day->format('Y-m-d'), $day];
        }

        if (is_string($value) && mb_strlen($value) > self::STATS_HASHED_TEXT) {
            return [md5($value), Str::limit($value, 4 * self::STATS_HASHED_TEXT)];
        }

        $key = match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => null,
        };

        return $key === null ? null : [$key, $value];
    }

    /**
     * What a column's menu says about its values: their list, or why there is
     * none — there are no values, each is unique, they are long texts, too
     * many, or of a kind that cannot be counted. It names the message.
     *
     * @param  array<string, mixed>  $total
     */
    protected function listing(array $total): string
    {
        return match (true) {
            $total['count'] === 0 => 'none',
            $total['unlisted'] => 'not-listed',
            $total['overflow'] => 'too-many',
            $total['count'] > 1 && count($total['values']) === $total['count'] => 'unique',
            $total['texts'] > 0 && $total['length'] / $total['texts'] > self::STATS_LONG_TEXT => 'long-text',
            default => 'values',
        };
    }

    /**
     * The counts of the values, the most frequent first; ties by the value,
     * so a reload keeps the order.
     *
     * @param  array<int|string, int>  $counts
     * @return array<int|string, int>
     */
    protected function rankedValues(array $counts): array
    {
        uksort($counts, fn ($a, $b) => $counts[$b] <=> $counts[$a] ?: $a <=> $b);

        return $counts;
    }

    /**
     * The rows the listed values first came from, keyed by their primary key:
     * a formatter may need its row, e.g. to link the value. Fetched in one
     * query, as the rows the counting walked through are gone.
     *
     * @param  array<string, array<string, mixed>>  $totals
     * @return Collection<int|string, Model>
     */
    protected function listedRows(array $totals): Collection
    {
        $keys = [];
        foreach ($totals as $total) {
            foreach (array_slice($total['ranked'], 0, self::STATS_VALUES_LIMIT, true) as $key => $count) {
                $keys[] = $total['rowKeys'][$key] ?? null;
            }
        }
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => $key !== null)));

        if (! $keys) {
            return collect();
        }

        return $this->getQuery()->whereKey($keys)->get()->keyBy(fn (Model $row) => $row->getKey());
    }

    /**
     * The most frequent values of a column rendered for its menu, with their
     * counts, and the count of the rest.
     *
     * @param  array<string, mixed>  $total
     * @param  Collection<int|string, Model>  $rows
     * @return array{listing: string, values: array<int, array{display: string, title: string, count: int}>, other: array{values: int, count: int}|null}
     */
    protected function valueListing(string $attribute, array $total, Collection $rows): array
    {
        $rest = array_slice($total['ranked'], self::STATS_VALUES_LIMIT, null, true);

        $values = [];
        foreach (array_slice($total['ranked'], 0, self::STATS_VALUES_LIMIT, true) as $key => $count) {
            $rowKey = $total['rowKeys'][$key] ?? null;
            $display = $this->valueDisplay($attribute, $total['samples'][$key], $rowKey === null ? null : $rows->get($rowKey));
            $values[] = ['display' => $display, 'title' => $this->plainText($display), 'count' => $count];
        }

        return [
            'listing' => $total['listing'],
            'values' => $values,
            'other' => $rest ? ['values' => count($rest), 'count' => array_sum($rest)] : null,
        ];
    }

    /**
     * The middle value of the numbers and the given count of zeros, or the
     * mean of the two middle values when there is an even number of them.
     *
     * @param  array<int, float>  $numbers
     */
    protected function median(array $numbers, int $zeros = 0): ?float
    {
        $values = array_merge($numbers, array_fill(0, $zeros, 0.0));
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        sort($values);
        $middle = intdiv($count, 2);

        return $count % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
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
     * The search input's name, unique per list: browsers keep their suggestions
     * per input name, so a shared name would mix the searches of every list.
     * The search bar comes with filters, and those require the session key.
     */
    public function searchInputName(): string
    {
        return 'mb-search-' . Str::slug($this->filterSessionKey);
    }

    /**
     * The statistics of one column, or null while none are loaded.
     *
     * @return array<string, mixed>|null
     */
    public function columnStats(string $attribute): ?array
    {
        return $this->stats[$attribute] ?? null;
    }

    /**
     * The statistics of one column, ready for its menu: each one's name, the
     * value as it is shown, and a second value — the share of all the rows for
     * the counts that have one, the non-zero counterpart in the paired group.
     * The statistics a column has nothing to say about are left out, and so
     * are the numeric ones, counts included, for a column that is not numeric.
     * `group` is the index in STATS_GROUPS.
     *
     * The paired group starts with a header row naming its two columns, or,
     * when the column has no zeros (see columnStatsHasNoZeros), saying so
     * across the whole row (`full`) over the values alone. A `wide` value
     * spans the second column too.
     *
     * @return array<int, array{key: string, label: string, display: string, second: ?string, wide: bool, full: bool, header: bool, group: int}>
     */
    public function columnStatsRows(string $attribute): array
    {
        $stats = $this->columnStats($attribute);

        if ($stats === null) {
            return [];
        }

        $noZeros = $this->columnStatsHasNoZeros($attribute);

        $rows = [];
        foreach (self::STATS_GROUPS as $group => $keys) {
            if (! $stats['numeric'] && in_array($group, self::STATS_NUMERIC_GROUPS, true)) {
                continue;
            }
            $paired = $group === self::STATS_PAIRED_GROUP;
            if ($paired) {
                $rows[] = [
                    'key' => 'header',
                    'label' => $noZeros ? __('model-browser::global.stats.no-zeros') : '',
                    'display' => $noZeros ? '' : __('model-browser::global.stats.all'),
                    'second' => $noZeros ? null : __('model-browser::global.stats.nonzero'),
                    'wide' => false,
                    'full' => $noZeros,
                    'header' => true,
                    'group' => $group,
                ];
            }
            foreach ($keys as $key) {
                $value = $stats[$key] ?? null;
                if ($value === null) {
                    continue;
                }
                $second = match (true) {
                    $paired && ! $noZeros => $this->statDisplay($attribute, self::STATS_NONZERO[$key], $stats[self::STATS_NONZERO[$key]]),
                    in_array($key, self::STATS_SHARES, true) => $this->share($value, $stats['rows']),
                    default => null,
                };
                $rows[] = [
                    'key' => $key,
                    'label' => __('model-browser::global.stats.labels.' . $key),
                    'display' => $this->statDisplay($attribute, $key, $value),
                    'second' => $second,
                    'wide' => in_array($key, self::STATS_WIDE, true) || ($paired && $noZeros),
                    'full' => false,
                    'header' => false,
                    'group' => $group,
                ];
            }
        }

        return $rows;
    }

    /**
     * The most frequent values of one column, ready for its menu, with a last
     * row counting the rest — or, when the values are not listed, the message
     * saying why. The values are aligned as the column is in the table.
     *
     * @return array{message: ?string, rows: array<int, array{label: string, title: string, count: string, share: string}>, align: string}
     */
    public function columnValueRows(string $attribute): array
    {
        $stats = $this->columnStats($attribute);

        if ($stats === null) {
            return ['message' => null, 'rows' => [], 'align' => 'start'];
        }

        if ($stats['listing'] !== 'values') {
            return ['message' => __('model-browser::global.stats.' . $stats['listing']), 'rows' => [], 'align' => 'start'];
        }

        $rows = [];
        foreach ($stats['values'] as $value) {
            $rows[] = [
                'label' => $value['display'],
                'title' => $value['title'],
                'count' => Number::format($value['count']),
                'share' => $this->share($value['count'], $stats['rows']),
            ];
        }

        if ($stats['other']) {
            $label = trans_choice('model-browser::global.stats.other-values', $stats['other']['values'], [
                'count' => Number::format($stats['other']['values']),
            ]);
            $rows[] = [
                'label' => e($label),
                'title' => $label,
                'count' => Number::format($stats['other']['count']),
                'share' => $this->share($stats['other']['count'], $stats['rows']),
            ];
        }

        return [
            'message' => null,
            'rows' => $rows,
            'align' => $this->alignments[$attribute] ?? ($stats['numeric'] ? 'end' : 'start'),
        ];
    }

    /**
     * Whether a numeric column has values and none of them is zero, so each
     * non-zero statistic equals its counterpart. False while no statistics
     * are loaded.
     */
    public function columnStatsHasNoZeros(string $attribute): bool
    {
        $stats = $this->columnStats($attribute);

        return $stats !== null && $stats['numeric'] && $stats['count'] === $stats['countnz'];
    }

    /**
     * One statistic rendered for display.
     *
     * Row counts are plain integers, and the span of a date column is shown as
     * its moments. The rest are in the column's own unit, so they go through
     * its `formats` callback when it has one — a formatter that needs the row
     * the value came from cannot render an aggregate, and falls back to a
     * plain number.
     */
    public function statDisplay(string $attribute, string $stat, int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (in_array($stat, self::STATS_COUNTS, true)) {
            return Number::format($value);
        }

        if (in_array($stat, self::STATS_DATES, true)) {
            $moment = Carbon::createFromTimestamp($value, config('app.timezone'));

            return $this->formatted($attribute, $moment) ?? e($moment->toDisplayTimezone()->isoFormat('L LT'));
        }

        return $this->formatted($attribute, $value) ?? (Number::format($value, maxPrecision: 2) ?: (string) $value);
    }

    /**
     * One of a column's values rendered for its menu: through the column's
     * `formats` callback when it has one, with a row the value came from, and
     * plainly otherwise — a date as its day, a labelled enum as its label.
     */
    protected function valueDisplay(string $attribute, mixed $value, ?Model $row = null): string
    {
        $formatted = $this->formatted($attribute, $value, $row);

        if ($formatted !== null) {
            return $formatted;
        }

        if ($value instanceof HasLabel && method_exists($value, 'toLabelHtml')) {
            return (string) $value->toLabelHtml();
        }

        return e(match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => Carbon::instance($value)->isoFormat('L'),
            is_bool($value) => __('model-browser::global.stats.' . ($value ? 'true' : 'false')),
            is_int($value), is_float($value) => Number::format($value, maxPrecision: 2) ?: (string) $value,
            default => (string) $value,
        });
    }

    /**
     * A value through the column's `formats` callback, or null when the column
     * has none or it fails, e.g. without a row.
     */
    protected function formatted(string $attribute, mixed $value, ?Model $row = null): ?string
    {
        $format = $this->formats[$attribute] ?? null;

        if (! $format) {
            return null;
        }

        try {
            return (string) $format($value, $row);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The markup of a value as the plain text a tooltip shows.
     */
    protected function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
    }

    /**
     * A count as a share of all the rows, to one decimal place.
     */
    protected function share(int $count, int $rows): string
    {
        return Number::percentage($rows ? $count / $rows * 100 : 0, precision: 1) ?: '';
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
                abort(413, trans('model-browser::global.download-csv.limit-exceeded', ['limit' => Number::format($this->exportLimit)]));
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
            // Excel reads a CSV without a BOM in the system's legacy encoding.
            fwrite($out, "\xEF\xBB\xBF");
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

    /**
     * The list's name and the time of the export in the display timezone, e.g.
     * `vouchers-2026-09-27-1430.csv`. The filter is left out: it can be too
     * complex to read well in a file name.
     */
    protected function generateExportFilename(): string
    {
        $name = Str::slug($this->exportName ?: Str::kebab(Str::pluralStudly(class_basename($this->model))));

        return $name . '-' . now()->toDisplayTimezone()->format('Y-m-d-Hi') . '.csv';
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
