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
    public array $alignments;

    #[Locked]
    public array $formats;

    #[Locked]
    public bool $enableSort = true;

    #[Locked]
    public array $defaultSort = [];  // new property

    #[Url(as: 'per-page')]
    public int $perPage = self::PER_PAGE_DEFAULT;

    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '', as: 'sort')]
    public array $sort = [];

    public function mount(
        string $model,
        array $filterAttributes = [],
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        array $defaultSort = [],
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
        $this->defaultSort = $defaultSort;
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
        foreach ($this->sort as $attribute => $direction) {
            if (in_array($attribute, $validAttributes) && in_array($direction, $validDirections)) {
                continue;
            }
            unset($this->sort[$attribute]);
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
        if ($this->filter) {
            $data = $data->filter(function ($item) {
                foreach ($this->filterAttributes as $attribute) {
                    $attributeFilter = $this->filter;
                    if (
                        isset($this->formats[$attribute])
                        && is_array($this->formats[$attribute])
                        && isset($this->formats[$attribute]['down'])
                    ) {
                        $attributeFilter = $this->formats[$attribute]['down']($this->filter);
                    }
                    $value = $this->itemValueStripped($item, $attribute);
                    // if filter containing asscetic characters, use as it is, otherwise remove asscent
                    if (str($attributeFilter)->ascii() == $attributeFilter) {
                        $value = str($value)->ascii();
                    }
                    if (stripos($value, $attributeFilter) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

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
                $sortByArg[] = fn ($a, $b) => $direction === 'desc'
                    ? $this->itemValueStripped($b, $attribute) <=> $this->itemValueStripped($a, $attribute)
                    : $this->itemValueStripped($a, $attribute) <=> $this->itemValueStripped($b, $attribute);
            }
            $data = $data->sortBy($sortByArg);
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
