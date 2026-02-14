<?php

namespace Internetguru\ModelBrowser\Components;

use Livewire\Attributes\Locked;

class TableModelBrowser extends BaseModelBrowser
{
    #[Locked]
    public int $lightDarkStep;

    #[Locked]
    public array $columnWidths;

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
        int $lightDarkStep = 1,
        array $columnWidths = [],
    ) {
        parent::mount($model, $viewAttributes, $formats, $alignments, $defaultSortColumn, $defaultSortDirection, $enableSort, $filters, $filterSessionKey, $refreshInterval);
        $this->lightDarkStep = $lightDarkStep;
        $this->columnWidths = $columnWidths;
    }

    /**
     * Generate grid template columns CSS value
     *
     * @return string
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

            return view('model-browser::livewire.table', [
                'data' => $data,
            ]);
        }

        return view('model-browser::livewire.table', [
            'data' => $this->getData(),
        ]);
    }
}
