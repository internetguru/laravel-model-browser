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
        array $filterAttributes = [],
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        array $defaultSort = [],
        bool $enableSort = true,
        array $sortComparators = [],
        int $lightDarkStep = 1,
        array $columnWidths = [],
    ) {
        parent::mount($model, $filterAttributes, $viewAttributes, $formats, $alignments, $defaultSort, $enableSort, $sortComparators);
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
        return view('model-browser::livewire.table', [
            'data' => $this->getData(),
        ]);
    }

}
