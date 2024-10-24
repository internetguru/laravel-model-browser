<?php

namespace Internetguru\ModelBrowser\Components;

use Illuminate\Support\Arr;
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
    ) {
        // if model contains @, split it into model and method
        if (str_contains($model, '@')) {
            [$model, $modelMethod] = explode('@', $model);
            $this->modelMethod = $modelMethod;
        }
        $this->model = $model;
        // Defaults to the first model's fillable attributes
        $this->viewAttributes = $viewAttributes ?? $model::first()?->getFillable() ?? [];
        $this->filterAttributes = $filterAttributes;
        $this->formats = $formats;
        $this->alignments = $alignments;
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
        $filterLength = mb_strlen($normalizedFilter);

        $data->getCollection()->transform(function ($item) use ($normalizedFilter) {
            // Highlight matches in each filter attribute
            foreach ($this->filterAttributes as $attribute) {
                $originalValue = $item->{$attribute . 'Formatted'} ?? $item->{$attribute};

                if (! $originalValue) {
                    continue;
                }

                // Apply highlighting while preserving HTML structure
                $item->{$attribute . 'Highlighted'} = $this->highlightText($originalValue, $normalizedFilter);
            }

            return $item;
        });

        return $data;
    }

    protected function highlightText($htmlContent, $normalizedFilter)
    {
        $dom = new \DOMDocument;

        // Suppress errors due to invalid HTML snippets
        libxml_use_internal_errors(true);
        // Load the HTML content
        $dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        // Traverse text nodes and apply highlighting
        $this->highlightDomNode($dom, $dom->documentElement, $normalizedFilter);

        // Save and return the modified HTML
        $body = $dom->getElementsByTagName('body')->item(0);
        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        return $innerHTML;
    }

    protected function highlightDomNode($dom, $node, $normalizedFilter)
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $originalText = $node->nodeValue;
            $normalizedText = mb_strtolower($this->removeAccents($originalText));

            if (mb_strpos($normalizedText, $normalizedFilter) !== false) {
                // Split the text and insert <mark> tags
                $newHtml = $this->addMarksAroundMatches($originalText, $normalizedFilter);
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($newHtml);
                $node->parentNode->replaceChild($fragment, $node);
            }
        } elseif ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->highlightDomNode($dom, $child, $normalizedFilter);
            }
        }
    }

    protected function addMarksAroundMatches($text, $normalizedFilter)
    {
        // Escape special HTML characters
        $escapedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Prepare the regex pattern
        $pattern = '/' . preg_quote($normalizedFilter, '/') . '/i';

        // Replace matches with <mark> tags
        $highlightedText = preg_replace_callback($pattern, function ($matches) {
            return '<mark>' . htmlspecialchars($matches[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</mark>';
        }, $escapedText);

        return $highlightedText;
    }

    protected function removeAccents($string)
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    }
}
