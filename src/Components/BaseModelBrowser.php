<?php

namespace Internetguru\ModelBrowser\Components;

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
    public string $modelMethod;

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
        if (! in_array($this->sortBy, $this->viewAttributes)) {
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

    protected function getData()
    {
        $filter = $this->filter;
        $escapedFilter = preg_quote($filter, '/');

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
        $data = $modelQuery->paginate($this->perPage);
        $data = $this->highlightMatches($data, $escapedFilter);

        return $data;
    }

    protected function highlightMatches($data, $escapedFilter)
    {
        if (! $escapedFilter) {
            return $data;
        }

        $normalizedFilter = mb_strtolower($this->removeAccents($escapedFilter));
        $data->getCollection()->transform(function ($item) use ($normalizedFilter) {
            // Highlight matches in each filter attribute
            foreach ($this->filterAttributes as $attribute) {
                $originalValue = $item->{$attribute};
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
                    $item->{$attribute} = $this->addMarksAroundMatches($originalValue, $positions, $filterLength);
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
