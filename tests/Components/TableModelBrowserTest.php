<?php

namespace Tests\Components;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    public function test_header_counts_the_filled_rows_of_a_partly_empty_column()
    {
        User::query()->delete();
        User::factory()->count(3)->create();
        User::factory()->create()->forceFill(['name' => ''])->save();

        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'statsAttributes' => ['name', 'email'],
        ]);

        // The slot is there from the start, holding each summarized column's width
        // with a spinner in it, so nothing moves once the count arrives
        $component->assertSeeHtml('model-browser__stats-filled');
        $this->assertSame(2, substr_count($component->html(), 'model-browser__stats-icon'));

        $component->call('loadTotalStats')->assertSeeHtml('(3)')
            ->assertDontSeeHtml('model-browser__stats-icon')
            ->assertSeeHtml('model-browser__stats-filled');

        // Every row has an e-mail, so its header has nothing to add
        $this->assertFalse($component->instance()->showsCountOfFilledRows('email'));
    }

    public function test_stats_menu_is_offered_only_on_the_configured_columns()
    {
        // A column nobody asked to summarize carries neither the menu nor the slot
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
        ])->assertDontSeeHtml('model-browser__stats-toggle')
            ->assertDontSeeHtml('model-browser__stats-filled');

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'statsAttributes' => ['name'],
        ])->assertSeeHtml('model-browser__stats-toggle')
            ->assertSeeHtml('model-browser__stats-filled')
            // One toggle, on the one configured column
            ->assertSeeHtmlInOrder(['grid-header-cell--stats', 'Name', 'grid-header-cell', 'Email']);
    }

    public function test_stats_menu_lists_every_statistic_of_a_numeric_column()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('score')->nullable();
        });

        User::query()->delete();
        User::factory()->create()->forceFill(['score' => 10])->save();
        User::factory()->create()->forceFill(['score' => 30])->save();

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['score' => 'Score'],
            'statsAttributes' => ['score'],
        ])->call('loadTotalStats')
            ->assertSeeHtmlInOrder(['SUM', 'AVG', 'MIN', 'MAX', 'COUNT', 'AVGNZ', 'MINNZ', 'COUNTNZ'])
            ->assertSee(__('model-browser::global.stats.copy'));
    }

    public function test_stats_menu_asks_for_narrower_filters_above_the_limit()
    {
        User::query()->delete();
        User::factory()->count(5)->create();

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'statsAttributes' => ['name'],
            'statsLimit' => 3,
        ])->call('loadTotalStats')
            ->assertSee(__('model-browser::global.stats.limit-exceeded', ['limit' => 3]))
            ->assertDontSeeHtml('model-browser__stats-list');
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
