<?php

namespace Internetguru\ModelBrowser\Components;

use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BaseModelBrowser extends Component
{
    use WithPagination;

    public const PER_PAGE_MIN = 3;
    public const PER_PAGE_MAX = 150;
    public const PER_PAGE_DEFAULT = 20;
    public const PER_PAGE_OPTIONS = [20, 50, 100];
    public const PER_PAGE_PREFERENCE = 'model_browser_per_page';

    // Search query security limits
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
     * Filter configuration.
     * Format: ['attribute' => ['type' => '...', 'label' => '...', ...]]
     *
     * Keys:
     * - type: Filter type (string, number, date, date_from, date_to, number_from, number_to, options)
     * - label: Display label
     * - column: Database column name (defaults to the attribute key)
     * - relation: Eloquent relation name — wraps the filter in whereHas()
     * - options: Array of options for 'options' type
     * - rules: Optional Laravel validation rules (overrides default type-based rules)
     * - url: Optional URL query parameter name to initialize filter from (takes priority over session)
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

    public int $perPage = self::PER_PAGE_DEFAULT;

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
            self::FILTER_DATE, self::FILTER_DATE_FROM, self::FILTER_DATE_TO => 'nullable|date',
            self::FILTER_OPTIONS => $this->getOptionsRule($config['options'] ?? []),
            default => 'nullable|string|max:255',
        };
    }

    /**
     * Generate validation rule for options filter.
     */
    protected function getOptionsRule(array $options): string
    {
        $validValues = [];
        foreach ($options as $optionKey => $optionValue) {
            if (is_array($optionValue) && isset($optionValue['id'])) {
                // Format: [['id' => 'value', 'name' => 'label'], ...]
                $validValues[] = $optionValue['id'];
            } elseif (is_numeric($optionKey)) {
                // Format: ['value1', 'value2', ...]
                $validValues[] = $optionValue;
            } else {
                // Format: ['value' => 'label', ...]
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
            fn($value, $key) => $value !== '' && $value !== null && ! $this->getErrorBag()->has('filter-' . $key),
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
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        foreach ($this->filterValues as $key => $value) {
            $this->filterValues[$key] = '';
        }
        $this->searchQuery = '';
        $this->totalCount = null;
        $this->resetErrorBag();
        $this->saveFiltersToSession();
        $this->resetPage();
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
            // Remove this key from search query, preserve rest
            $terms = $this->parseSearchTerms($this->searchQuery);
            $filtered = array_filter($terms, fn ($t) => $t['key'] !== $attribute);
            $this->searchQuery = $this->buildSearchQueryFromTerms($filtered);
            $this->saveFiltersToSession();
            $this->resetPage();
        }
    }

    /**
     * Apply search query — parse, sync filter fields, save.
     */
    public function applySearch(): void
    {
        $this->searchQuery = $this->sanitizeSearchQuery($this->searchQuery);

        // Populate filterValues from parsed terms (for filter panel sync)
        foreach ($this->filterConfig as $attr => $config) {
            $this->filterValues[$attr] = '';
        }
        foreach ($this->parseSearchTerms($this->searchQuery) as $term) {
            if ($term['key'] !== null && isset($this->filterConfig[$term['key']])) {
                $this->filterValues[$term['key']] = $term['value'];
            }
        }

        $this->totalCount = null;
        $this->resetErrorBag();
        $this->saveFiltersToSession();
        $this->resetPage();
    }

    /**
     * Sanitize search query input against abuse.
     */
    protected function sanitizeSearchQuery(string $query): string
    {
        // Remove null bytes and control characters
        $query = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $query);
        // Collapse whitespace
        $query = preg_replace('/\s+/', ' ', trim($query));

        return mb_substr($query, 0, self::SEARCH_MAX_LENGTH);
    }

    /**
     * Parse search query into structured terms.
     * Returns array of ['key' => string|null, 'value' => string, 'exact' => bool]
     * - key:value or key:"quoted value" → specific filter term
     * - "quoted text" → exact match free text term (key = null, exact = true)
     * - bare word → free text term (key = null, searches all string columns)
     */
    protected function parseSearchTerms(string $query): array
    {
        $query = $this->sanitizeSearchQuery($query);
        $terms = [];

        // Extract key:"quoted value" and key:value tokens
        $remaining = preg_replace_callback('/(\w+):(?:"([^"]*)"|([^\s"]+))/', function ($match) use (&$terms) {
            $key = $match[1];
            $value = ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');
            $value = mb_substr(trim($value), 0, 255);
            if ($value !== '' && isset($this->filterConfig[$key])) {
                $terms[] = ['key' => $key, 'value' => $value, 'exact' => false];
            }

            return '';
        }, $query);

        // Extract "quoted text" (exact match free text)
        $remaining = preg_replace_callback('/"([^"]*)"/', function ($match) use (&$terms) {
            $value = mb_substr(trim($match[1]), 0, 255);
            if ($value !== '') {
                $terms[] = ['key' => null, 'value' => $value, 'exact' => true];
            }

            return '';
        }, $remaining);

        // Remaining text → free text terms (each word is an OR term)
        foreach (preg_split('/\s+/', trim($remaining), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $word = mb_substr(trim($word), 0, 255);
            if ($word !== '') {
                $terms[] = ['key' => null, 'value' => $word, 'exact' => false];
            }
        }

        return array_slice($terms, 0, self::SEARCH_MAX_TERMS);
    }

    /**
     * Build search query string from current filter values.
     */
    protected function buildSearchQuery(): string
    {
        $parts = [];

        foreach ($this->filterValues as $attr => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $parts[] = str_contains($value, ' ') ? "{$attr}:\"{$value}\"" : "{$attr}:{$value}";
        }

        return implode(' ', $parts);
    }

    /**
     * Build search query string from structured terms array.
     */
    protected function buildSearchQueryFromTerms(array $terms): string
    {
        $parts = [];
        foreach ($terms as $term) {
            if ($term['key'] === null) {
                $parts[] = ($term['exact'] ?? false) ? '"' . $term['value'] . '"' : $term['value'];
            } else {
                $parts[] = str_contains($term['value'], ' ')
                    ? "{$term['key']}:\"{$term['value']}\""
                    : "{$term['key']}:{$term['value']}";
            }
        }

        return implode(' ', $parts);
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
        }
    }

    /**
     * Reload filters from session — used during poll to ensure the table
     * reflects stored (saved) filters, not unsaved UI edits.
     */
    protected function loadFiltersFromSession(): void
    {
        if (empty($this->filterSessionKey)) {
            return;
        }

        $sessionFilters = session($this->filterSessionKey, []);
        $sessionQuery = session($this->filterSessionKey . '.query', '');

        foreach ($this->filterConfig as $attribute => $config) {
            $value = $sessionFilters[$attribute] ?? '';
            $result = $this->validateFilterValue($attribute, $value);
            $this->filterValues[$attribute] = $result['value'];
        }

        $this->searchQuery = $sessionQuery ?: $this->buildSearchQuery();
    }

    /**
     * Load total result count asynchronously.
     */
    public function loadTotalCount(): void
    {
        $query = $this->getQuery();
        $this->applyFiltersToQuery($query);
        $this->totalCount = $query->count();
    }

    public function paginationView()
    {
        return 'model-browser::empty';
    }

    public function paginationSimpleView()
    {
        return 'model-browser::empty';
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

    public function render()
    {
        if ($this->refreshInterval > 0) {
            // Save user's unsaved UI state
            $uiFilterValues = $this->filterValues;
            $uiSearchQuery = $this->searchQuery;

            // Use stored (saved) filters for the data query
            $this->loadFiltersFromSession();
            $this->loadTotalCount();
            $data = $this->getData();

            // Restore user's unsaved edits so the UI is not cleared
            $this->filterValues = $uiFilterValues;
            $this->searchQuery = $uiSearchQuery;

            return view('model-browser::livewire.base', [
                'data' => $data,
            ]);
        }

        return view('model-browser::livewire.base', [
            'data' => $this->getData(),
        ]);
    }

    public function getAlignment(string $attribute, mixed $value): string
    {
        return $this->alignments[$attribute] ?? (is_numeric($value) ? 'end' : 'start');
    }

    public function downloadCsv(): StreamedResponse
    {
        $data = $this->getData(paginate: false);
        $headers = array_values($this->viewAttributes);
        $handle = fopen('php://memory', 'w+');

        fputcsv($handle, $headers);
        foreach ($data as $item) {
            $row = [];
            foreach ($this->viewAttributes as $attribute => $trans) {
                $row[] = $this->itemValueStripped($item, $attribute);
            }
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        $exportName = $this->generateExportFilename();

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
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
        return $this->modelMethod
            ? $this->model::{$this->modelMethod}()
            : $this->model::query();
    }

    /**
     * Auto-apply search terms to the query.
     * Parses searchQuery into terms. All terms are AND'd together.
     * Free text (no key:) searches all string-type filter columns (OR within one term).
     * Column defaults to the filter attribute key when not explicitly set.
     */
    protected function applyFiltersToQuery(Builder $query): void
    {
        $terms = $this->parseSearchTerms($this->searchQuery);

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
                $searchableColumns[] = [
                    'column' => $config['column'],
                    'relation' => $config['relation'] ?? null,
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
                            $this->applyCondition($q, $col['column'], $col['relation'], self::FILTER_STRING, $term['value']);
                        });
                    }
                });
            } else {
                // Specific filter — skip filters without explicit 'column'
                $config = $this->filterConfig[$term['key']] ?? [];
                if (! array_key_exists('column', $config)) {
                    continue;
                }
                $this->applyCondition(
                    $query,
                    $config['column'],
                    $config['relation'] ?? null,
                    $config['type'] ?? self::FILTER_STRING,
                    $term['value']
                );
            }
        }
    }

    /**
     * Apply a single filter condition with AND semantics.
     */
    protected function applyCondition(Builder $query, string $column, ?string $relation, string $type, string $value): void
    {
        $applyWhere = function (Builder $q) use ($column, $type, $value) {
            try {
                match ($type) {
                    self::FILTER_STRING => $q->whereLikeUnaccented($column, $value),
                    self::FILTER_DATE_FROM => $q->where($column, '>=', Carbon::parse($value)->startOfDay()),
                    self::FILTER_DATE_TO => $q->where($column, '<=', Carbon::parse($value)->endOfDay()),
                    self::FILTER_NUMBER_FROM => $q->where($column, '>=', $value),
                    self::FILTER_NUMBER_TO => $q->where($column, '<=', $value),
                    self::FILTER_DATE, self::FILTER_NUMBER, self::FILTER_OPTIONS => $q->where($column, $value),
                    default => $q->whereLikeUnaccented($column, $value),
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
     * Get data with database-level sorting and pagination.
     */
    protected function getData(bool $paginate = true, bool $applyFormats = true): Paginator|Collection
    {
        $query = $this->getQuery();

        // Auto-apply filters that have 'column' configured
        $this->applyFiltersToQuery($query);

        // Apply database-level sorting (single column)
        // User sorting only when enableSort is true, default sort always applies
        $sortColumn = $this->enableSort ? $this->getActiveSortColumn() : $this->defaultSortColumn;
        $sortDirection = $this->enableSort ? $this->getActiveSortDirection() : $this->defaultSortDirection;
        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        if ($paginate) {
            // Use simplePaginate for better performance (no total count query)
            $data = $query->simplePaginate($this->perPage);

            if ($applyFormats) {
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
        return $data->transform(function ($item) {
            foreach ($this->formats as $attribute => $format) {
                $value = Arr::get($item, $attribute);
                if ($value === null) {
                    continue;
                }
                $item->{$attribute . 'Formatted'} = $format($value, $item);
            }

            return $item;
        });
    }
}
