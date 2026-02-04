<?php

namespace Internetguru\ModelBrowser\Components;

use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
     * Format: ['attribute' => ['type' => 'string|number|date|...', 'label' => 'Label', 'options' => [...], 'rules' => 'nullable|...', 'url' => 'query_param']]
     *
     * - type: Filter type (string, number, date, date_from, date_to, number_from, number_to, options)
     * - label: Display label
     * - options: Array of options for 'options' type
     * - rules: Optional Laravel validation rules (overrides default type-based rules)
     * - url: Optional URL query parameter name to initialize filter from (takes priority over session)
     */
    #[Locked]
    public array $filterConfig = [];

    #[Url(as: 'per-page')]
    public int $perPage = self::PER_PAGE_DEFAULT;

    // #[Url(except: '', as: 'sort-column')]
    public string $sortColumn = '';

    // #[Url(except: '', as: 'sort-direction')]
    public string $sortDirection = 'asc';

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
     * Priority: URL query parameter > Session > empty
     */
    protected function initializeFilters(): void
    {
        // Load from session
        $sessionFilters = session($this->filterSessionKey, []);
        $urlParamsToClear = [];

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

            // Fall back to session if no URL value
            if ($value === '') {
                $value = $sessionFilters[$attribute] ?? '';
            }

            $result = $this->validateFilterValue($attribute, $value);
            $this->filterValues[$attribute] = $result['value'];
            // Don't show errors on initial load
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
     * Save filters to session (only valid values).
     */
    protected function saveFiltersToSession(): void
    {
        $validFilters = [];
        foreach ($this->filterValues as $key => $value) {
            if (! $this->getErrorBag()->has('filter-' . $key) && $value !== '' && $value !== null) {
                $validFilters[$key] = $value;
            }
        }
        session([$this->filterSessionKey => $validFilters]);
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
            $this->resetErrorBag('filter-' . $attribute);
            $this->saveFiltersToSession();
            $this->resetPage();
        }
    }

    /**
     * Apply filters - validate all and save to session.
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
            $this->saveFiltersToSession();
            $this->resetPage();
        }

        // Dispatch event with active filter keys for Alpine to update UI
        $this->dispatch('mb-filters-applied', active: array_keys($this->getActiveFilters()));
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
        $this->perPage = min(self::PER_PAGE_MAX, max(self::PER_PAGE_MIN, $this->perPage));
        $this->resetPage();
    }

    public function render()
    {
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
