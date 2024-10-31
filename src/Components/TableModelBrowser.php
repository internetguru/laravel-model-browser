<?php

namespace Internetguru\ModelBrowser\Components;

use Livewire\Attributes\Locked;

class TableModelBrowser extends BaseModelBrowser
{
    #[Locked]
    public int $lightDarkStep;

    public function mount(
        string $model,
        array $filterAttributes = [],
        array $viewAttributes = [],
        array $formats = [],
        array $alignments = [],
        string $defaultSortBy = '',
        string $defaultSortDirection = 'asc',
        int $lightDarkStep = 1,
    ) {
        parent::mount($model, $filterAttributes, $viewAttributes, $formats, $alignments, $defaultSortBy, $defaultSortDirection);
        $this->lightDarkStep = $lightDarkStep;
    }

    public function render()
    {
        return view('model-browser::livewire.table', [
            'data' => $this->getData(),
        ]);
    }
}
