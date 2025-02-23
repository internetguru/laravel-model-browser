<?php

namespace Internetguru\ModelBrowser\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Internetguru\ModelBrowser\Traits\HighlightMatchesTrait;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class BaseModelBrowser extends Component
{
    use HighlightMatchesTrait;
    use WithPagination;

    public const PER_PAGE_MIN = 3;

    public const PER_PAGE_MAX = 150;

    public const PER_PAGE_DEFAULT = 50;

    #[Locked]
    public string $model;

    #[Locked]
    public string $modelMethod = '';

    #[Locked]
    public array $viewAttributes;

    #[Locked]
    public array $filterAttributes;

    #[Locked]
    public array $formats;

    #[Locked]
    public string $defaultSortBy;

    #[Locked]
    public string $enableSort;

    #[Locked]
    public string $defaultSortDirection;

    #[Url(as: 'per-page')]
    public int $perPage = self::PER_PAGE_DEFAULT;

    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '', as: 'sort')]
    public string $sortBy = '';

    #[Url(except: '', as: 'direction')]
    public string $sortDirection = 'asc';

    public function mount(
        string $model,
        array $filterAttributes = [],
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        string $defaultSortBy = '',
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
        $this->filterAttributes = $filterAttributes;
        $this->formats = $formats;
        $this->alignments = $alignments;
        $this->enableSort = $enableSort;
        if (! $this->sortBy && $this->enableSort) {
            $this->sortBy = $defaultSortBy;
            $this->sortDirection = $defaultSortDirection;
        }
        $this->updatedPerPage();
        if ($this->enableSort) {
            $this->updatedSortBy();
            $this->updatedSortByDirection();
        } else {
            $this->sortDirection = '';
        }
    }

    public function paginationView()
    {
        return 'model-browser::empty';
    }

    public function updatedSortBy()
    {
        if (! array_key_exists($this->sortBy, $this->viewAttributes)) {
            $this->sortBy = '';
        }
    }

    public function updatedSortByDirection()
    {
        if (! $this->sortBy) {
            $this->sortDirection = '';

            return;
        }
        if (! in_array($this->sortDirection, ['asc', 'desc'])) {
            $this->sortDirection = 'asc';
        }
    }

    public function updatedPerPage()
    {
        $this->perPage = min(self::PER_PAGE_MAX, max(self::PER_PAGE_MIN, $this->perPage));
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
        $data = $this->getData(paginate: false, highlightMatches: false, applyFormats: false);
        $headers = array_values($this->viewAttributes);
        $handle = fopen('php://memory', 'w+');

        // Write data to the memory stream
        fputcsv($handle, $headers);
        foreach ($data as $item) {
            $row = [];
            foreach ($this->viewAttributes as $attribute => $trans) {
                $row[] = prettyPrint(Arr::get($item, $attribute));
            }
            fputcsv($handle, $row);
        }

        // Rewind the memory stream to the beginning and capture the CSV content
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        // Stream the CSV content as a download
        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, 'data.csv', ['Content-Type' => 'text/csv']);
    }

    protected function getData(bool $paginate = true, bool $highlightMatches = true, bool $applyFormats = true)
    {
        $filter = $this->filter;

        // Retrieve all items from the model
        $modelQuery = $this->modelMethod
            ? $this->model::{$this->modelMethod}()
            : $this->model::query();

        $data = $modelQuery->get();

        if ($data->count() === 0) {
            return $data;
        }

        if ($applyFormats) {
            $data = $this->format($data);
        }

        // Filter the collection
        if ($filter) {
            $data = $data->filter(function ($item) use ($filter) {
                foreach ($this->filterAttributes as $attribute) {
                    $attributeFilter = $filter;
                    if (
                        isset($this->formats[$attribute])
                        && is_array($this->formats[$attribute])
                        && isset($this->formats[$attribute]['down'])
                    ) {
                        $attributeFilter = $this->formats[$attribute]['down']($filter);
                    }
                    $value = isset($item->{$attribute . 'Formatted'})
                        ? strip_tags($item->{$attribute . 'Formatted'})
                        : $item->{$attribute};
                    if (stripos(str($value)->ascii(), str($attributeFilter)->ascii()) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Sort the collection
        if ($this->sortBy) {
            if ($this->sortDirection === 'desc') {
                $data = $data->sortByDesc(function ($item) {
                    return $item->{$this->sortBy};
                });
            } else {
                $data = $data->sortBy(function ($item) {
                    return $item->{$this->sortBy};
                });
            }
        }

        // Paginate manually if required
        if ($paginate) {
            $currentPage = $this->getPage();
            $currentItems = $data->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();
            $data = new LengthAwarePaginator(
                $currentItems,
                $data->count(),
                $this->perPage,
                $currentPage
            );
        }

        if ($highlightMatches) {
            // When paginated, highlight on the underlying collection
            if ($paginate) {
                $data->setCollection(
                    $this->highlightMatches($data->getCollection(), $this->filter, $this->filterAttributes)
                );
            } else {
                $data = $this->highlightMatches($data, $this->filter, $this->filterAttributes);
            }
        }

        return $data;
    }

    protected function format($data)
    {
        $data->transform(function ($item) {
            foreach ($this->formats as $attribute => $format) {
                if (is_array($format)) {
                    if (! isset($format['up'])) {
                        continue;
                    }
                    $format = $format['up'];
                }
                if (! isset($item->{$attribute})) {
                    continue;
                }
                $item->{$attribute . 'Formatted'} = $format($item->{$attribute}, $item);
            }

            return $item;
        });

        return $data;
    }
}
