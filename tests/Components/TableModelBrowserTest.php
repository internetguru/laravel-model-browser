<?php

namespace Tests\Components;

use Internetguru\ModelBrowser\Components\TableModelBrowser;
use Livewire\Livewire;
use Tests\TestCase;

class TableModelBrowserTest extends TestCase
{
    public function test_can_mount_with_default_values()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
        ])->assertSet('model', \App\Models\User::class)
            ->assertSet('lightDarkStep', 1);
    }

    public function test_renders_correct_view()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
        ])->assertViewIs('model-browser::livewire.table');
    }

    public function test_empty_result_caused_by_filters_offers_reset()
    {
        \App\Models\User::query()->delete();
        \App\Models\User::factory()->create(['name' => 'Zenon Unique']);

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-table-filters',
        ]);

        $component->set('filterValues.name', 'NoSuchName')
            ->call('applyFilters')
            ->assertSee(__('model-browser::global.no-results-filtered'))
            ->assertSee(__('model-browser::global.reset-filters'));
    }

    public function test_empty_result_without_filters_shows_plain_message()
    {
        \App\Models\User::query()->delete();

        Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSee(__('model-browser::global.no-results'))
            ->assertDontSee(__('model-browser::global.reset-filters'));
    }

    public function test_renders_copy_page_button()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => \App\Models\User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSee(__('model-browser::global.copy-page.label'))
            ->assertSeeHtml('copyPage()');
    }
}
