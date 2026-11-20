<?php

namespace Internetguru\ModelBrowser\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

class TableModelBrowser extends BaseModelBrowser
{
    #[Locked]
    public int $lightDarkStep;

    /** @var array<string, string> */
    #[Locked]
    public array $columnWidths;

    /**
     * @param  array<string, string>  $viewAttributes
     * @param  array<string, string>  $exportAttributes
     * @param  array<string, callable>  $formats
     * @param  array<string, callable>  $rawFormats
     * @param  array<string, string>  $alignments
     * @param  array<string, array<string, mixed>>  $filters
     * @param  array<int|string, string|Closure>  $with
     * @param  list<string>|null  $statsAttributes
     * @param  list<string>  $statsTextAttributes
     * @param  array<string, string>  $columnWidths
     * @return void
     */
    public function mount(
        string $model,
        array $viewAttributes = [],
        array $exportAttributes = [],
        array $formats = [],
        array $rawFormats = [],
        array $alignments = [],
        string $defaultSortColumn = '',
        string $defaultSortDirection = 'asc',
        bool $enableSort = true,
        array $filters = [],
        string $filterSessionKey = '',
        int $refreshInterval = 0,
        array $with = [],
        ?int $exportLimit = null,
        ?array $statsAttributes = null,
        ?int $statsLimit = null,
        string $exportName = '',
        array $statsTextAttributes = [],
        string $title = '',
        int $lightDarkStep = 1,
        array $columnWidths = [],
    ) {
        parent::mount($model, $viewAttributes, $exportAttributes, $formats, $rawFormats, $alignments, $defaultSortColumn, $defaultSortDirection, $enableSort, $filters, $filterSessionKey, $refreshInterval, $with, $exportLimit, $statsAttributes, $statsLimit, $exportName, $statsTextAttributes, $title);
        $this->lightDarkStep = $lightDarkStep;
        $this->columnWidths = $columnWidths;
    }

    /**
     * Generate grid template columns CSS value
     */
    public function generateGridColumns(): string
    {
        if (empty($this->viewAttributes)) {
            return '';
        }

        $columns = [];
        foreach (array_keys($this->viewAttributes) as $column) {
            $columns[] = $this->columnWidths[$column] ?? 'minmax(4em, 1fr)';
        }

        return implode(' ', $columns);
    }

    /**
     * @return View
     */
    public function render()
    {
        return view('model-browser::livewire.table');
    }
}
