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

    public function test_stats_load_when_a_menu_is_first_opened()
    {
        $component = Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ]);

        $this->assertDoesNotMatchRegularExpression('/<button[^>]*model-browser__stats-toggle[^>]*disabled/', $component->html());
        $component->assertSeeHtml("\$dispatch('mb-load-stats')")
            ->assertSeeHtml('x-on:mb-load-stats.window')
            ->assertSeeHtml('spinner-border')
            ->assertDontSeeHtml('model-browser__stats-toggle--ready');

        $component->call('loadTotalStats')
            ->assertSeeHtml('model-browser__stats-toggle--ready')
            ->assertDontSeeHtml('model-browser__stats-toggle--unavailable')
            ->assertDontSeeHtml('spinner-border');
    }

    public function test_stats_menu_is_offered_only_on_the_configured_columns()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
        ])->assertSeeHtmlInOrder(['grid-header-cell--stats', 'Name', 'grid-header-cell--stats', 'Email']);

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'statsAttributes' => [],
        ])->assertDontSeeHtml('model-browser__stats-toggle');

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'statsAttributes' => ['name'],
        ])->assertSeeHtml('model-browser__stats-toggle')
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
        User::factory()->create()->forceFill(['score' => 0])->save();

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['score' => 'Score'],
            'statsAttributes' => ['score'],
        ])->call('loadTotalStats')
            ->assertSeeHtmlInOrder([
                '">DISTINCT</dt>', '">EMPTY</dt>', '">NON-EMPTY</dt>',
                'model-browser__stats-group-start">SUM</dt>', 'model-browser__stats-wide',
                'model-browser__stats-group-start">AVG</dt>', '">MEDIAN</dt>', '">MIN</dt>', '">MAX</dt>', '">COUNT</dt>',
                'model-browser__stats-group-start">AVGNZ</dt>', '">MEDIANNZ</dt>', '">MINNZ</dt>', '">MAXNZ</dt>', '">COUNTNZ</dt>',
            ])
            ->assertDontSee(__('model-browser::global.stats.no-zeros'))
            ->assertSee(__('model-browser::global.stats.copy'))
            // Both copy buttons copy what is shown, not the raw values
            ->assertSeeHtml('copyPage()')
            ->assertDontSeeHtml("getAttribute('data-raw')");
    }

    public function test_stats_menu_notes_a_column_without_zeros_instead_of_its_non_zero_group()
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
            ->assertSeeHtml('">COUNT</dt>')
            ->assertDontSeeHtml('">AVGNZ</dt>')
            ->assertSee(__('model-browser::global.stats.no-zeros'));
    }

    public function test_stats_menu_lists_the_most_frequent_values()
    {
        User::query()->delete();
        foreach (['Anna', 'Bob', 'Anna', ''] as $name) {
            User::factory()->create()->forceFill(['name' => $name])->save();
        }

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->call('loadTotalStats')
            ->assertSeeHtmlInOrder([
                '">EMPTY</dt>', '<dd', '1</dd>', '25.0%</dd>',
                'model-browser__stats-values',
                '<dt title="Anna">Anna</dt>', '2</dd>', '50.0%</dd>',
                '<dt title="Bob">Bob</dt>', '1</dd>', '25.0%</dd>',
            ]);
    }

    public function test_stats_menu_says_why_the_values_are_not_listed()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['email' => 'Email'],
        ])->call('loadTotalStats')
            ->assertSee(__('model-browser::global.stats.unique'))
            ->assertDontSeeHtml('model-browser__stats-values');
    }

    public function test_stats_menu_is_headed_by_the_list_and_the_column()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'title' => 'Users',
        ])->assertSeeHtmlInOrder(['model-browser__stats-heading', 'Users /', '<strong>Name</strong>']);

        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertDontSee('/ Name')
            ->assertSeeHtml('<strong>Name</strong>');
    }

    public function test_stats_menu_gives_the_dates_the_share_column_too()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['created_at' => 'Created'],
        ])->call('loadTotalStats')
            ->assertSeeHtmlInOrder([
                '">EARLIEST</dt>', 'model-browser__stats-wide',
                '">LATEST</dt>', 'model-browser__stats-wide',
            ]);
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
            ->assertDontSeeHtml('model-browser__stats-list')
            ->assertSeeHtml('model-browser__stats-toggle--unavailable')
            ->assertDontSeeHtml('model-browser__stats-toggle--ready');
    }

    public function test_stats_limit_reads_with_thousands_separators()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'statsAttributes' => ['name'],
            'statsLimit' => 1500,
        ])->set('statsOverLimit', true)
            ->assertSee('To show stats, reduce results below 1,500 using filters.')
            ->assertDontSeeHtml('model-browser__stats-list');
    }

    public function test_renders_copy_page_button()
    {
        Livewire::test(TableModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSee(__('model-browser::global.copy-page.label'))
            ->assertSeeHtml('copyPage()')
            // The copied header reads the label alone, not the statistics menu beside it
            ->assertSeeHtml('<span class="grid-header-label">Name</span>')
            ->assertSeeHtml("querySelector('.grid-header-label')");
    }
}
