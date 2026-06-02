<?php

namespace Tests\Components;

use App\Models\User;
use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Livewire\Livewire;
use Tests\TestCase;

class BaseModelBrowserTest extends TestCase
{
    public function test_can_mount_with_default_values()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
        ])->assertSet('model', User::class)
            ->assertSet('modelMethod', '')
            ->assertSet('perPage', BaseModelBrowser::PER_PAGE_DEFAULT);

        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class . '@summary',
        ])->assertSet('model', User::class)
            ->assertSet('modelMethod', 'summary')
            ->assertSet('perPage', BaseModelBrowser::PER_PAGE_DEFAULT);
    }

    public function test_updates_pagination_correctly()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
        ]);

        // Assert default 'perPage' value
        $component->assertSet('perPage', BaseModelBrowser::PER_PAGE_DEFAULT);

        // Set 'perPage' property and assert
        $component->set('perPage', 30);
        $component->assertSet('perPage', 30);

        // Set 'perPage' to a value outside the allowed range and assert it is corrected
        $component->set('perPage', 200);
        $component->assertSet('perPage', BaseModelBrowser::PER_PAGE_MAX);

        $component->set('perPage', 1);
        $component->assertSet('perPage', BaseModelBrowser::PER_PAGE_MIN);
    }

    public function test_renders_correct_view()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
        ])->assertViewIs('model-browser::livewire.base');
    }

    public function test_download_csv()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
        ])->call('downloadCsv')
            ->assertFileDownloaded();
    }

    public function test_model_view()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
            ],
        ]);

        $component->assertSee('Name');
        $component->assertSee('Email');
        $component->assertSee('Created At');
        $component->assertSee('Updated At');

        // see user data
        $users = User::all();
        foreach ($users as $user) {
            $component->assertSee($user->name);
            $component->assertSee($user->email);
            $component->assertSee($user->created_at);
            $component->assertSee($user->updated_at);
        }
    }

    public function test_model_view_with_formats()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
            ],
            'formats' => [
                'created_at' => 'formatDateTime',
            ],
        ]);

        // see formatted date
        $users = User::all();
        foreach ($users as $user) {
            $component->assertSee($user->created_at->format('Y-m-d H:i:s'));
        }
    }

    public function test_sorting_single_column()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
            ],
        ]);

        // Set sort column
        $component->set('sortColumn', 'name');
        $component->assertSet('sortColumn', 'name');
        $component->assertSet('sortDirection', 'asc');

        // Change direction
        $component->set('sortDirection', 'desc');
        $component->assertSet('sortDirection', 'desc');

        // Invalid sort column should be cleared
        $component->set('sortColumn', 'invalid_column');
        $component->assertSet('sortColumn', '');
    }

    public function test_default_sort_settings()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
            ],
            'defaultSortColumn' => 'name',
            'defaultSortDirection' => 'desc',
        ]);

        $component->assertSet('defaultSortColumn', 'name');
        $component->assertSet('defaultSortDirection', 'desc');
    }

    public function test_mounts_with_filters_and_renders_search_bar()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ])->assertSet('filterSessionKey', 'test-mb-filters')
            ->assertViewIs('model-browser::livewire.base');
    }

    public function test_mounting_with_filters_requires_session_key()
    {
        $this->expectException(\Exception::class);

        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
        ]);
    }

    public function test_search_query_filters_results_by_column()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Zenon Unique', 'email' => 'zenon@example.com']);
        User::factory()->count(5)->create();

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        $component->set('searchQuery', 'Zenon')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 1);
        $component->assertSee('Zenon Unique');
    }

    public function test_apply_filters_builds_search_query()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        $component->set('filterValues.name', 'Alice')->call('applyFilters');
        $component->assertSet('searchQuery', 'name:Alice');
    }

    public function test_clear_filters_resets_search_query()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        $component->set('searchQuery', 'name:Alice')->call('applySearch');
        $component->assertSet('filterValues.name', 'Alice');

        $component->call('clearFilters');
        $component->assertSet('searchQuery', '')
            ->assertSet('filterValues.name', '');
    }

    public function test_total_count_is_deferred_on_initial_render()
    {
        // The count is loaded by the "count" island after the initial render,
        // not during mount/render, so it starts as null (placeholder).
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSet('totalCount', null);
    }

    public function test_changing_filters_dispatches_count_refresh()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        // Each filter mutation must tell the count island to reload itself.
        $component->set('searchQuery', 'Alice')->call('applySearch')
            ->assertDispatched('mb-refresh-count')
            ->assertSet('totalCount', null);

        $component->call('clearFilters')
            ->assertDispatched('mb-refresh-count');

        $component->set('filterValues.name', 'Bob')->call('applyFilters')
            ->assertDispatched('mb-refresh-count');
    }
}
