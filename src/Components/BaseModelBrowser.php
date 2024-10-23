<?php

namespace Internetguru\ModelBrowser\Components;

use Illuminate\Support\Arr;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BaseModelBrowser extends Component
{
    use WithPagination;

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

    #[Url(as: 'per-page')]
    public int $perPage = 9;

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
        array $formats = []
    ) {
        // if model contains @, split it into model and method
        if (str_contains($model, '@')) {
            [$model, $modelMethod] = explode('@', $model);
            $this->modelMethod = $modelMethod;
        }
        $this->model = $model;
        // Defaults to the first model's fillable attributes
        $this->viewAttributes = $viewAttributes ?? $model::first()?->getFillable() ?? [];
        $this->formats = $formats;
        $this->updatedPerPage();
        $this->updatedSortBy();
        $this->updatedSortByDirection();
    }

    public function updatedSortBy()
    {
        if (! array_key_exists($this->sortBy, $this->viewAttributes)) {
            $this->sortBy = '';
        }
    }

    public function updatedSortByDirection()
    {
        if (! in_array($this->sortDirection, ['asc', 'desc'])) {
            $this->sortDirection = 'asc';
        }
    }

    public function updatedPerPage()
    {
        $this->perPage = min(120, max(3, $this->perPage));
    }

    public function render()
    {
        return view('model-browser::livewire.base', [
            'data' => $this->getData(),
        ]);
    }

    public function downloadCsv()
    {
        $data = $this->getData(paginate: false, highlightMatches: false, applyFormats: false);
        $headers = array_values($this->viewAttributes);
        $handle = fopen('php://memory', 'w+');

        // Write data to the memory stream
        fputcsv($handle, $headers);
        foreach ($data as $item) {
            $row = [];
            foreach ($this->viewAttributes as $attribute => $trans) {
                $row[] = Arr::get($item, $attribute);
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

        // Query the model with the filter
        $modelQuery = $this->modelMethod
            ? $this->model::{$this->modelMethod}()
            : $this->model::query();
        $modelQuery->where(function ($query) use ($filter) {
            if (! $filter) {
                return $query;
            }
            foreach ($this->filterAttributes as $attribute) {
                $query->orWhere($attribute, 'like', '%' . $filter . '%');
            }
        });

        // Sort the results
        if ($this->sortBy) {
            $modelQuery->orderBy($this->sortBy, $this->sortDirection);
        }

        // Paginate the results and highlight matches
        $data = $paginate ? $modelQuery->paginate($this->perPage) : $modelQuery->get();
        if ($applyFormats) {
            $data = $this->format($data);
        }
        if ($highlightMatches) {
            $data = $this->highlightMatches($data);
        }

        return $data;
    }

    protected function format($data)
    {
        $data->transform(function ($item) {
            foreach ($this->formats as $attribute => $format) {
                if (! $item->{$attribute}) {
                    continue;
                }
                $item->{$attribute . 'Formatted'} = $format($item->{$attribute}, $item);
            }

            return $item;
        });

        return $data;
    }

    protected function highlightMatches($data)
    {
        if (! $this->filter) {
            return $data;
        }

        $normalizedFilter = mb_strtolower($this->removeAccents($this->filter));
        $data->getCollection()->transform(function ($item) use ($normalizedFilter) {
            // Highlight matches in each filter attribute
            foreach ($this->filterAttributes as $attribute) {
                $originalValue = $item->{$attribute . 'Formatted'} ?? $item->{$attribute};
                $normalizedValue = mb_strtolower($this->removeAccents($originalValue));

                // Find positions of the filter in the normalized text
                $positions = [];
                $offset = 0;
                $filterLength = mb_strlen($normalizedFilter);
                while (($pos = mb_strpos($normalizedValue, $normalizedFilter, $offset)) !== false) {
                    $positions[] = $pos;
                    $offset = $pos + $filterLength;
                }

                // Highlight matches in the original text
                if (! empty($positions)) {
                    $item->{$attribute . 'Highlighted'} = $this->addMarksAroundMatches($originalValue, $positions, $filterLength);
                }
            }

            return $item;
        });

        return $data;
    }

    protected function removeAccents($string)
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    }

    protected function addMarksAroundMatches($text, $positions, $length)
    {
        $result = '';
        $prevEnd = 0;
        foreach ($positions as $pos) {
            // Append the text before the match
            $result .= mb_substr($text, $prevEnd, $pos - $prevEnd);
            // Append the highlighted match
            $result .= '<mark>' . mb_substr($text, $pos, $length) . '</mark>';
            $prevEnd = $pos + $length;
        }
        // Append the remaining text
        $result .= mb_substr($text, $prevEnd);

        return $result;
    }
}
