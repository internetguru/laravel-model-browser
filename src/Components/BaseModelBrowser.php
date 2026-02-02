<?php

namespace Internetguru\ModelBrowser\Components;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    #[Url(as: 'per-page')]
    public int $perPage = self::PER_PAGE_DEFAULT;

    // #[Url(except: '', as: 'sort-column')]
    public string $sortColumn = '';

    // #[Url(except: '', as: 'sort-direction')]
    public string $sortDirection = 'asc';

    public function mount(
        string $model,
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        string $defaultSortColumn = '',
        string $defaultSortDirection = 'asc',
        bool $enableSort = true,
    ) {
        // if model contains @, split it into model and method
        if (str_contains($model, '@')) {
            [$model, $modelMethod] = explode('@', $model);
            $this->modelMethod = $modelMethod;
        }
        $this->model = $model;

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
        $this->updatedPerPage();
        $this->updatedSortColumn();
        $this->updatedSortDirection();
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
        return isset($item->{$attribute . 'Formatted'})
            ? $item->{$attribute . 'Formatted'}
            : $item->{$attribute};
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
