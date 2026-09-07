<?php

namespace Tests\Components;

use App\Models\User;
use Internetguru\ModelBrowser\Components\TableModelBrowser;
use Livewire\Livewire;
use Tests\TestCase;

class TableModelBrowserTest extends TestCase
{
    public function test_can_mount_with_default_values()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
        ])->assertSet('model', User::class)
            ->assertSet('lightDarkStep', 1);
    }

    public function test_renders_correct_view()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
        ])->assertViewIs('model-browser::livewire.table');
    }

    public function test_empty_result_caused_by_filters_offers_reset()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Zenon Unique']);

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
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
        User::query()->delete();

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSee(__('model-browser::global.no-results'))
            ->assertDontSee(__('model-browser::global.reset-filters'));
    }

    public function test_pagination_shows_the_window_and_the_total_on_one_line()
    {
        User::query()->delete();
        User::factory()->count(25)->create();

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ]);

        // The window is rendered inline; the total arrives from the "count" island
        $component->assertSeeHtml('1–20')
            ->assertSeeHtml(__('model-browser::pagination.of'))
            ->assertSeeHtml('model-browser__count');

        // Once loaded, the total lands on that same line
        $component->call('loadTotalCount')
            ->assertSet('totalCount', 25)
            ->assertSeeHtml('25');
    }

    public function test_pagination_no_longer_offers_a_page_limit_select()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertDontSeeHtml('per-page-select')
            ->assertDontSeeHtml('wire:change="setPerPage');
    }

    public function test_the_page_shows_a_default_page_and_grows_only_on_demand()
    {
        User::query()->delete();
        User::factory()->count(50)->create();

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ]);

        $component->assertSet('perPage', TableModelBrowser::PER_PAGE_DEFAULT)
            ->assertSet('extraRows', 0)
            ->assertSee(__('model-browser::pagination.load-more'))
            ->assertSeeHtml('1–20');

        // Loading more grows this page only — the page size itself is untouched
        $component->call('loadMore')
            ->assertSet('perPage', TableModelBrowser::PER_PAGE_DEFAULT)
            ->assertSet('extraRows', TableModelBrowser::PER_PAGE_STEP)
            ->assertSeeHtml('1–40');
    }

    public function test_the_arrows_move_by_a_default_page_and_drop_the_extra_rows()
    {
        User::query()->delete();
        // Enough rows for every window below to be full
        User::factory()->count(70)->create();

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ]);

        $component->call('loadMore')->assertSeeHtml('1–40');

        // Next steps on by one default page, not past everything that was loaded
        $component->call('nextPage')
            ->assertSet('skip', TableModelBrowser::PER_PAGE_DEFAULT)
            ->assertSet('extraRows', 0)
            ->assertSeeHtml('21–40');

        $component->call('loadMore')->assertSeeHtml('21–60');

        $component->call('previousPage')
            ->assertSet('skip', 0)
            ->assertSet('extraRows', 0)
            ->assertSeeHtml('1–20');
    }

    public function test_changing_the_filters_drops_the_extra_rows()
    {
        User::query()->delete();
        User::factory()->count(50)->create();

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-load-more-filters',
        ]);

        $component->call('loadMore')->assertSet('extraRows', TableModelBrowser::PER_PAGE_STEP);

        $component->set('searchQuery', 'a')->call('applySearch')
            ->assertSet('extraRows', 0);
    }

    public function test_load_more_is_not_offered_on_the_last_page()
    {
        User::query()->delete();
        User::factory()->count(3)->create();

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSeeHtml('1–3')
            ->assertDontSee(__('model-browser::pagination.load-more'));
    }

    public function test_load_more_never_grows_the_page_past_the_maximum()
    {
        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ]);

        $steps = (int) ceil(TableModelBrowser::PER_PAGE_MAX / TableModelBrowser::PER_PAGE_STEP) + 1;
        for ($i = 0; $i < $steps; $i++) {
            $component->call('loadMore');
        }

        $component->assertSet(
            'extraRows',
            TableModelBrowser::PER_PAGE_MAX - TableModelBrowser::PER_PAGE_DEFAULT,
        );
        $this->assertSame(TableModelBrowser::PER_PAGE_MAX, $component->instance()->windowSize());
    }

    public function test_renders_copy_page_button()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSee(__('model-browser::global.copy-page.label'))
            ->assertSeeHtml('copyPage()');
    }
}
