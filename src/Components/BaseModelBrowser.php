<?php

namespace Internetguru\ModelBrowser\Components;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
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

    public const PER_PAGE_PREFERENCE = 'model_browser_per_page';

    // Search query security limits — override trait constants
    public const SEARCH_MAX_LENGTH = 500;

    public const SEARCH_MAX_TERMS = 20;

    // Filter types
    public const FILTER_STRING = 'string';

    public const FILTER_NUMBER = 'number';

    public const FILTER_DATE = 'date';

    public const FILTER_DATE_FROM = 'date_from';

    public const FILTER_DATE_TO = 'date_to';

    public const FILTER_NUMBER_FROM = 'number_from';

    public const FILTER_NUMBER_TO = 'number_to';

    public const FILTER_OPTIONS = 'options';

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
     *
     * Keys:
     * - type: Filter type (string, number, date, date_from, date_to, number_from, number_to, options)
     * - label: Display label
     * - column: Database column name (defaults to the attribute key)
     * - relation: Eloquent relation name — wraps the filter in whereHas()
     * - options: Array of options for 'options' type
     * - restrict: (bool) For 'options' type — restrict values to options list (default: false). When false, allows any string and uses LIKE matching.
     * - rules: Optional Laravel validation rules (overrides default type-based rules)
     * - url: Optional URL query parameter name to initialize filter from (takes priority over session)
     * - timezone: Timezone for date filters — parsed date is shifted via Carbon::shiftTimezone($tz)
     * - ascii_fast: (bool) Column stores only ASCII (e-mail, login, slug, …). On SQLite, skips the unaccent() PHP UDF in LIKE matching for a large speedup on big tables.
     *
     * When 'column' is set, filters are auto-applied to the query.
     * When 'column' is omitted, the filter is NOT auto-applied (use HasModelBrowserFilters trait for manual access).
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

    public int $perPage = self::PER_PAGE_DEFAULT;

    #[Url(as: 'skip', except: 0)]
    public int $skip = 0;

    // #[Url(except: '', as: 'sort-column')]
    public string $sortColumn = '';

    // #[Url(except: '', as: 'sort-direction')]
    public string $sortDirection = 'asc';

    /**
     * Search query string with Gmail-style syntax (e.g. "name:John role:admin").
     */
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

    public function mount(
        string $model,
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        string $defaultSortColumn = '',
        string $defaultSortDirection = 'asc',
        bool $enableSort = true,
        array $filters = [],
        string $filterSessionKey = '',
        int $refreshInterval = 0,
        array $with = [],
        ?int $exportLimit = null,
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
        $this->formats = $formats;
        $this->alignments = $alignments;
        $this->enableSort = $enableSort;
        $this->defaultSortColumn = $defaultSortColumn;
        $this->defaultSortDirection = $defaultSortDirection;
        $this->filterConfig = $filters;
        $this->refreshInterval = $refreshInterval;
        $this->with = $with;
        $this->exportLimit = $exportLimit ?? (int) config('model-browser.export_limit');
        if (! empty($filters) && ! $filterSessionKey) {
            throw new Exception('Provide filterSessionKey when using filters configuration.');
        }
        $this->initializeFilters();
        // Read per-page from user preference
        if (auth()->check()) {
            $preferred = auth()->user()->getPreference(self::PER_PAGE_PREFERENCE);
            $this->perPage = (int) ($preferred ?? self::PER_PAGE_DEFAULT);
        }
        $this->updatedPerPage();
        $this->updatedSortColumn();
        $this->updatedSortDirection();
    }

    /**
     * Initialize filter values from URL, session, or defaults.
     * Priority: URL query parameter > Session > empty
     * When any URL filter is present, all other filters are cleared.
     */
    protected function initializeFilters(): void
    {
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

            // Fall back to session only if no URL filters are present
            if ($value === '' && ! $hasUrlFilters) {
                $value = $sessionFilters[$attribute] ?? '';
            }

            $result = $this->validateFilterValue($attribute, $value);
            $this->filterValues[$attribute] = $result['value'];
            // Don't show errors on initial load
        }

        // Build or restore search query
        if ($hasUrlFilters) {
            $this->searchQuery = $this->buildSearchQuery();
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

        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => $rules]
        );

        if ($validator->fails()) {
            return [
                'value' => (string) $value,
                'error' => $validator->errors()->first($attribute),
            ];
        }

        return ['value' => (string) $value, 'error' => null];
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
            self::FILTER_NUMBER, self::FILTER_NUMBER_FROM, self::FILTER_NUMBER_TO => 'nullable|numeric',
            self::FILTER_DATE, self::FILTER_DATE_FROM, self::FILTER_DATE_TO => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9 .:\/+\-]+$/iu'],
            self::FILTER_OPTIONS => ! empty($config['restrict'])
                ? $this->getOptionsRule($config['options'] ?? [])
                : 'nullable|string|max:255',
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
     * Hook called after filters/search are changed.
     */
    protected function onFiltersChanged(): void
    {
        $this->totalCount = null;
        $this->resetErrorBag();
        $this->saveFiltersToSession();
        $this->resetPage();
        // Reload the count island only (the data query re-runs in this same
        // request via the rows() computed, so the count must not).
        $this->dispatch('mb-refresh-count');
    }

    /**
     * Clear a specific filter.
     */
    public function clearFilter(string $attribute): void
    {
        if (isset($this->filterValues[$attribute])) {
            $this->filterValues[$attribute] = '';
            $this->totalCount = null;
            $this->resetErrorBag('filter-' . $attribute);
            $terms = $this->parseSearchTerms($this->searchQuery);
            $filtered = array_filter($terms, fn ($t) => $t['key'] !== $attribute);
            $this->searchQuery = $this->buildSearchQueryFromTerms($filtered);
            $this->saveFiltersToSession();
            $this->resetPage();
            $this->dispatch('mb-refresh-count');
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
            $this->totalCount = null;
            $this->saveFiltersToSession();
            $this->resetPage();
            $this->dispatch('mb-refresh-count');
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
        $this->skip = max(0, $this->skip - $this->perPage);
    }

    public function nextPage(): void
    {
        $this->skip += $this->perPage;
    }

    public function resetPage(): void
    {
        $this->skip = 0;
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

    public function setPerPage(int $value): void
    {
        $this->perPage = $value;
        $this->updatedPerPage();
        if (auth()->check()) {
            auth()->user()->setPreference(self::PER_PAGE_PREFERENCE, $this->perPage);
        }
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
    public function rows(): Paginator|Collection
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
    public function downloadCsv(): StreamedResponse
    {
        $exportName = $this->generateExportFilename();
        $headers = array_values($this->viewAttributes);
        $attributes = array_keys($this->viewAttributes);
        $query = $this->buildFilteredSortedQuery();

        // The export button is disabled client-side when the result count
        // exceeds the export limit, but the download endpoint is directly
        // POSTable — enforce the limit here as well.
        if ($this->exportLimit > 0 && $query->clone()->toBase()->getCountForPagination() > $this->exportLimit) {
            abort(413, trans('model-browser::global.download-csv.limit-exceeded', ['limit' => $this->exportLimit]));
        }

        // Offset-based chunking (lazy) needs a deterministic order; fall back
        // to the primary key when no sort column is active.
        $hasOrder = ! empty($query->getQuery()->orders);
        if (! $hasOrder) {
            $query->orderBy((new $this->model)->getKeyName());
        }

        return response()->streamDownload(function () use ($headers, $query, $attributes) {
            // Large exports can easily exceed max_execution_time, and since
            // headers are already sent, the resulting error dump would end up
            // inside the downloaded CSV.
            @set_time_limit(0);

            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            // cursor() streams rows from a single query, but does not support
            // eager loading — fall back to offset chunking when relations are
            // requested.
            $rows = empty($this->with) ? $query->cursor() : $query->lazy(500);

            $count = 0;
            foreach ($rows as $item) {
                $this->formatItem($item);
                $row = [];
                foreach ($attributes as $attribute) {
                    $row[] = $this->itemValueStripped($item, $attribute);
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
     * Column defaults to the filter attribute key when not explicitly set.
     */
    protected function applyFiltersToQuery(Builder $query, ?string $searchQuery = null): void
    {
        $terms = $this->parseSearchTerms($searchQuery ?? $this->searchQuery);

        if (empty($terms)) {
            return;
        }

        // Collect searchable columns for free text search (only filters with explicit 'column')
        $searchableColumns = [];
        foreach ($this->filterConfig as $attr => $config) {
            if (! array_key_exists('column', $config)) {
                continue;
            }
            $type = $config['type'] ?? self::FILTER_STRING;
            if (in_array($type, [self::FILTER_STRING, self::FILTER_OPTIONS])) {
                $preprocessor = $config['preprocessor'] ?? null;
                $searchableColumns[] = [
                    'column' => $config['column'],
                    'relation' => $config['relation'] ?? null,
                    'preprocessor' => ($preprocessor && \function_exists($preprocessor)) ? $preprocessor : null,
                    'ascii_fast' => ! empty($config['ascii_fast']),
                ];
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
                // Specific filter — skip filters without explicit 'column'
                $config = $this->filterConfig[$term['key']] ?? [];
                if (! array_key_exists('column', $config)) {
                    continue;
                }
                // Skip invalid filter values
                $result = $this->validateFilterValue($term['key'], $term['value']);
                if ($result['error']) {
                    continue;
                }
                $type = $config['type'] ?? self::FILTER_STRING;
                if ($type === self::FILTER_OPTIONS && empty($config['restrict'])) {
                    $type = self::FILTER_STRING;
                }
                $preprocessor = $config['preprocessor'] ?? null;
                $value = ($preprocessor && \function_exists($preprocessor)) ? $preprocessor($term['value']) : $term['value'];
                $this->applyCondition(
                    $query,
                    $config['column'],
                    $config['relation'] ?? null,
                    $type,
                    $value,
                    $config['timezone'] ?? null,
                    ! empty($config['ascii_fast']),
                );
            }
        }
    }

    /**
     * Apply a single filter condition with AND semantics.
     */
    protected function applyCondition(Builder $query, string $column, ?string $relation, string $type, string $value, ?string $timezone = null, bool $asciiFast = false): void
    {
        $applyWhere = function (Builder $q) use ($column, $type, $value, $timezone, $asciiFast) {
            try {
                $parseDate = function (string $v) use ($timezone) {
                    $date = Carbon::parse($v);
                    if ($timezone) {
                        $date = $date->shiftTimezone($timezone)->timezone(config('app.timezone', 'UTC'));
                    }

                    return $date;
                };
                match ($type) {
                    self::FILTER_STRING => $q->whereLikeUnaccented($column, $value, $asciiFast),
                    self::FILTER_DATE_FROM => $q->where($column, '>=', $parseDate($value)),
                    self::FILTER_DATE_TO => $q->where($column, '<=', (function () use ($value, $timezone) {
                        $date = Carbon::parse($value);
                        if ($date->format('H:i:s') === '00:00:00') {
                            $date = $date->endOfDay();
                        }
                        if ($timezone) {
                            $date = $date->shiftTimezone($timezone)->timezone(config('app.timezone', 'UTC'));
                        }

                        return $date;
                    })()),
                    self::FILTER_NUMBER_FROM => $q->where($column, '>=', $value),
                    self::FILTER_NUMBER_TO => $q->where($column, '<=', $value),
                    self::FILTER_DATE => $q->where($column, $parseDate($value)),
                    self::FILTER_NUMBER, self::FILTER_OPTIONS => $q->where($column, $value),
                    default => $q->whereLikeUnaccented($column, $value, $asciiFast),
                };
            } catch (\Exception $e) {
                // Invalid value (e.g. unparseable date), skip
            }
        };

        if ($relation) {
            $parts = explode('.', $relation);
            $nested = $applyWhere;
            foreach (array_reverse($parts) as $part) {
                $inner = $nested;
                $nested = fn (Builder $q) => $q->whereHas($part, $inner);
            }
            $nested($query);
        } else {
            $applyWhere($query);
        }
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
     */
    protected function getData(bool $paginate = true, bool $applyFormats = true): Paginator|Collection
    {
        $query = $this->buildFilteredSortedQuery();

        if ($paginate) {
            // Clamp skip to a valid page boundary and derive page number
            $this->skip = max(0, intval($this->skip / $this->perPage) * $this->perPage);
            $page = intval($this->skip / $this->perPage) + 1;
            Paginator::defaultSimpleView($this->paginationSimpleView());
            $data = $query->simplePaginate($this->perPage, ['*'], 'page', $page);

            if ($applyFormats && $data instanceof Paginator) {
                $data->setCollection($this->format($data->getCollection()));
            }

            return $data;
        }

        // For CSV export, get all data
        $data = $query->get();

        if ($applyFormats) {
            $data = $this->format($data);
        }

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

    public function itemValueStripped($item, $attribute)
    {
        return strip_tags($this->itemValue($item, $attribute));
    }

    protected function format(Collection $data): Collection
    {
        return $data->transform(fn ($item) => $this->formatItem($item));
    }

    protected function formatItem($item)
    {
        foreach ($this->formats as $attribute => $format) {
            $value = Arr::get($item, $attribute);
            if ($value === null) {
                continue;
            }
            $item->{$attribute . 'Formatted'} = $format($value, $item);
        }

        return $item;
    }
}
