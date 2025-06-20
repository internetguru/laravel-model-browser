<?php

namespace Internetguru\ModelBrowser\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Internetguru\ModelBrowser\Traits\HighlightMatchesTrait;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BaseModelBrowser extends Component
{
    use HighlightMatchesTrait;
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
    public array $filterAttributes;

    #[Locked]
    public array $alignments;

    #[Locked]
    public array $formats;

    #[Locked]
    public bool $enableSort = true;

    #[Locked]
    public array $defaultSort = [];

    #[Locked]
    public array $sortComparators = [];

    #[Url(as: 'per-page')]
    public int $perPage = self::PER_PAGE_DEFAULT;

    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '', as: 'sort')]
    public array $sort = [];

    public string $filterColumn = 'all';

    public function mount(
        string $model,
        array $filterAttributes = [],
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        array $defaultSort = [],
        bool $enableSort = true,
        array $sortComparators = [],
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
        $this->defaultSort = $defaultSort;
        $this->sortComparators = $sortComparators;
        $this->updatedPerPage();
    }

    public function paginationView()
    {
        return 'model-browser::empty';
    }

    public function updatedSort()
    {
        $validAttributes = array_keys($this->viewAttributes);
        $validDirections = ['asc', 'desc'];
        foreach ($this->sort as $attribute => $compare) {
            if (in_array($attribute, $validAttributes) && in_array($compare, $validDirections)) {
                continue;
            }
            unset($this->sort[$attribute]);
        }
    }

    public function updatedFilter()
    {
        $this->resetPage();
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
        $data = $this->getData(paginate: false, highlightMatches: false);
        $headers = array_values($this->viewAttributes);
        $handle = fopen('php://memory', 'w+');

        // Write data to the memory stream
        fputcsv($handle, $headers);
        foreach ($data as $item) {
            $row = [];
            foreach ($this->viewAttributes as $attribute => $trans) {
                $row[] = $this->itemValueStripped($item, $attribute);
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
        // Retrieve all items from the model
        $data = $this->modelMethod
            ? $this->model::{$this->modelMethod}()
            : $this->model::query()->get();

        if ($data->count() === 0) {
            return $data;
        }

        // Append missing attributes
        foreach ($this->viewAttributes as $attribute => $trans) {
            if (! isset($data[0]->{$attribute})) {
                $data[0]->{$attribute} = null;
            }
        }

        $applyFormatsOnAll = ! empty($this->filterAttributes) || $this->enableSort;

        if ($applyFormats && $applyFormatsOnAll) {
            $data = $this->format($data);
        }

        if (! empty($this->filterAttributes) && $this->filter) {
            $data = $this->applyFilter($data);
        }

        if ($this->enableSort) {
            // Multi-column sort
            $sort = $this->sort;
            foreach ($this->defaultSort as $attribute => $direction) {
                if (! isset($sort[$attribute])) {
                    $sort[$attribute] = $direction;
                }
            }
            if (! empty($sort)) {
                $sortByArg = [];
                foreach ($sort as $attribute => $direction) {
                    if (is_callable($this->sortComparators[$attribute][$direction] ?? null)) {
                        $sortByArg[] = Closure::fromCallable($this->sortComparators[$attribute][$direction]);
                    } else {
                        $sortByArg[] = fn ($a, $b) => $direction === 'desc'
                            ? str($this->itemValueStripped($b, $attribute))->ascii() <=> str($this->itemValueStripped($a, $attribute))->ascii()
                            : str($this->itemValueStripped($a, $attribute))->ascii() <=> str($this->itemValueStripped($b, $attribute))->ascii();
                    }
                }
                $data = $data->sortBy($sortByArg);
            }
        }

        // Paginate manually if required
        if ($paginate) {
            if ($data->count() > 0) {
                $currentPage = $this->getPage();
                $currentItems = $data->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();
                $data = new LengthAwarePaginator(
                    $currentItems,
                    $data->count(),
                    $this->perPage,
                    $currentPage
                );
            } else {
                $data = new LengthAwarePaginator([], 0, $this->perPage, 1);
            }
        }

        if ($applyFormats && ! $applyFormatsOnAll) {
            // Apply formats only to the current page if not applied on all
            if ($paginate) {
                $data->setCollection(
                    $this->format($data->getCollection())
                );
            } else {
                $data = $this->format($data);
            }
        }

        if ($highlightMatches) {
            $highlightedColumns = $this->filterColumn == 'all' ? $this->filterAttributes : [$this->filterColumn];
            if ($paginate) {
                $data->setCollection(
                    $this->highlightMatches($data->getCollection(), $this->filter, $highlightedColumns)
                );
            } else {
                $data = $this->highlightMatches($data, $this->filter, $highlightedColumns);
            }
        }

        return $data;
    }

    public function itemValue($item, $attribute)
    {
        return isset($item->{$attribute . 'Formatted'})
            ? $item->{$attribute . 'Formatted'}
            : $item->{$attribute};
    }

    public function itemValueHighlighted($item, $attribute)
    {
        return Arr::get($item, $attribute . 'Highlighted')
            ?? Arr::get($item, $attribute . 'Formatted')
            ?? prettyPrint(Arr::get($item, $attribute));
    }

    public function itemValueStripped($item, $attribute)
    {
        return strip_tags($this->itemValue($item, $attribute));
    }

    protected function format($data)
    {
        $data->transform(function ($item) {
            foreach ($this->formats as $attribute => $format) {
                $value = Arr::get($item, $attribute);
                if ($value === null) {
                    continue;
                }
                $item->{$attribute . 'Formatted'} = $format($value, $item);
            }

            return $item;
        });

        return $data;
    }

    protected function applyFilter($data): Collection
    {
        return $data->filter(function ($item) {
            foreach ($this->filterAttributes as $attribute) {
                $attributeFilter = $this->filter;
                $exactMatch = $this->exactMatchFilter($attributeFilter);

                if ($this->filterColumn !== 'all' && $attribute !== $this->filterColumn) {
                    continue;
                }

                $value = $this->itemValueStripped($item, $attribute);

                // For exact match with empty string
                if ($exactMatch && $attributeFilter === '' && $value === '') {
                    return true;
                }

                if ($exactMatch) {
                    // Exact matching
                    if ($value == $attributeFilter) {
                        return true;
                    }
                } else {
                    // Fuzzy matching with both case insensitivity and ASCII insensitivity
                    // Check for match without ASCII conversion first
                    $attributeFilter = trim($attributeFilter);
                    if (mb_stripos($value, $attributeFilter) !== false) {
                        return true;
                    }

                    $normalizedValue = str($value)->ascii();
                    $normalizedFilter = str($attributeFilter)->ascii();
                    if (mb_stripos($normalizedValue, $normalizedFilter) !== false) {
                        return true;
                    }
                }
            }
            return false;
        });
    }
}
