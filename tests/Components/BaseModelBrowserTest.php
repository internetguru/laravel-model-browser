<?php

namespace Tests\Components;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\ViewException;
use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Internetguru\ModelBrowser\Traits\HasModelBrowserFilters;
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

    public function test_download_csv_rejected_over_export_limit()
    {
        config(['model-browser.export_limit' => 1]);

        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
        ])->call('downloadCsv')
            ->assertStatus(413);
    }

    public function test_export_limit_instance_param_overrides_config()
    {
        config(['model-browser.export_limit' => 1]);

        // Instance param above the seeded row count → allowed
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
            'exportLimit' => 100,
        ])->assertSet('exportLimit', 100)
            ->call('downloadCsv')
            ->assertFileDownloaded();

        // Instance param below the seeded row count → rejected
        config(['model-browser.export_limit' => 1500]);
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
            'exportLimit' => 5,
        ])->call('downloadCsv')
            ->assertStatus(413);
    }

    public function test_download_csv_allowed_with_export_limit_disabled()
    {
        config(['model-browser.export_limit' => 0]);

        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
        ])->call('downloadCsv')
            ->assertFileDownloaded();
    }

    public function test_download_csv_stream_endpoint()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('model-browser.download-csv'), [
                'snapshot' => json_encode($component->snapshot),
                'token' => 'testtoken123',
            ]);

        $response->assertOk();
        $response->assertDownload();
        $response->assertCookie('mb_csv_download', 'testtoken123', encrypted: false);

        $content = $response->streamedContent();
        $this->assertStringContainsString('Name,Email', $content);
        foreach (User::all() as $user) {
            $this->assertStringContainsString($user->email, $content);
        }
    }

    public function test_export_attributes_are_exported_but_not_displayed()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
            ],
            'exportAttributes' => [
                'email' => 'Email',
            ],
        ]);

        $component->assertSee('Name');
        $component->assertDontSee('Email');
        foreach (User::all() as $user) {
            $component->assertDontSee($user->email);
        }

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('model-browser.download-csv'), [
                'snapshot' => json_encode($component->snapshot),
            ]);

        $content = $response->streamedContent();
        $this->assertStringContainsString('Name,Email', $content);
        foreach (User::all() as $user) {
            $this->assertStringContainsString($user->email, $content);
        }
    }

    public function test_export_attribute_repositions_a_visible_column()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'created_at' => 'Created At',
            ],
            'exportAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('model-browser.download-csv'), [
                'snapshot' => json_encode($component->snapshot),
            ]);

        $this->assertStringStartsWith('"Created At",Name,Email', $response->streamedContent());
    }

    public function test_download_csv_stream_endpoint_rejects_tampered_snapshot()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
                'email' => 'Email',
            ],
        ]);

        $snapshot = $component->snapshot;
        $snapshot['data']['model'] = 'App\\Models\\SomethingElse';

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('model-browser.download-csv'), [
                'snapshot' => json_encode($snapshot),
            ])
            ->assertForbidden();
    }

    public function test_download_csv_stream_endpoint_rejects_invalid_payload()
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post(route('model-browser.download-csv'), [
                'snapshot' => 'not-json',
            ])
            ->assertBadRequest();
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

    public function test_filter_names_must_be_kebab_case()
    {
        foreach (['createdBy', 'created_by', 'created-', '-created', 'created--by', 'created2'] as $name) {
            try {
                Livewire::test(BaseModelBrowser::class, [
                    'model' => User::class,
                    'filters' => [
                        $name => ['type' => 'string', 'label' => 'Name'],
                    ],
                    'filterSessionKey' => 'test-mb-filter-name',
                ]);
                $this->fail("Filter name '{$name}' was accepted.");
            } catch (ViewException $e) {
                $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
                $this->assertStringContainsString("'{$name}'", $e->getMessage());
            }
        }
    }

    public function test_checkbox_filter_round_trips_through_the_search_query()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'pending' => ['type' => 'checkbox', 'label' => 'Pending'],
            ],
            'filterSessionKey' => 'test-mb-checkbox',
        ]);

        // A checked box arrives from Livewire as a boolean and is stored as "1"
        $component->set('filterValues.pending', true)->call('applyFilters');
        $component->assertSet('filterValues.pending', '1')
            ->assertSet('searchQuery', 'pending:1');
        $this->assertSame('1', session('test-mb-checkbox')['pending']);

        $component->set('filterValues.pending', false)->call('applyFilters');
        $component->assertSet('filterValues.pending', '')
            ->assertSet('searchQuery', '');
    }

    public function test_checkbox_filter_with_a_column_matches_the_flagged_rows()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(0);
        });

        User::query()->delete();
        User::factory()->create(['name' => 'Flagged Person'])->forceFill(['is_flagged' => 1])->save();
        User::factory()->create(['name' => 'Plain Person']);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'flagged' => ['type' => 'checkbox', 'label' => 'Flagged', 'column' => 'is_flagged'],
            ],
            'filterSessionKey' => 'test-mb-checkbox-column',
        ]);

        $component->call('loadTotalCount')->assertSet('totalCount', 2);

        $component->set('filterValues.flagged', true)->call('applyFilters');
        $component->call('loadTotalCount')->assertSet('totalCount', 1);
        $component->assertSee('Flagged Person')->assertDontSee('Plain Person');
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

    public function test_empty_search_term_finds_the_rows_the_filter_has_no_value_on()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Named Person', 'email' => 'named@example.com']);
        User::factory()->create(['name' => '', 'email' => 'blank@example.com']);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-empty-filters',
        ]);

        foreach (['name:', 'name:""'] as $search) {
            $component->set('searchQuery', $search)->call('applySearch');

            $component->call('loadTotalCount')->assertSet('totalCount', 1);
            $component->assertSee('blank@example.com')
                ->assertDontSee('named@example.com');
        }
    }

    public function test_an_empty_search_term_survives_a_round_trip_through_the_filter_panel()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-empty-roundtrip',
        ]);

        $component->set('searchQuery', 'name:""')->call('applySearch')
            ->assertSet('filterValues.name', BaseModelBrowser::FILTER_EMPTY);

        // Re-applying from the filter panel keeps the term, written the short way
        $component->call('applyFilters')->assertSet('searchQuery', 'name:');
    }

    public function test_empty_search_term_over_a_relation_finds_the_rows_without_a_value_on_it()
    {
        User::query()->delete();
        $titled = User::factory()->create(['name' => 'Has A Titled Post']);
        $untitled = User::factory()->create(['name' => 'Has An Untitled Post']);
        User::factory()->create(['name' => 'Has No Post']);
        $titled->posts()->create(['title' => 'Something']);
        $untitled->posts()->create(['title' => null]);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'post' => ['type' => 'string', 'label' => 'Post', 'column' => 'title', 'relation' => 'posts'],
            ],
            'filterSessionKey' => 'test-mb-empty-relation',
        ]);

        $component->set('searchQuery', 'post:')->call('applySearch');

        // A missing relation and a relation carrying no value both count as empty
        $component->call('loadTotalCount')->assertSet('totalCount', 2);
        $component->assertSee('Has No Post')
            ->assertSee('Has An Untitled Post')
            ->assertDontSee('Has A Titled Post');
    }

    public function test_a_bare_key_means_no_value_only_for_a_filter()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-bare-key',
        ]);

        $component->set('searchQuery', 'name: Alice')->call('applySearch')
            ->assertSet('filterValues.name', BaseModelBrowser::FILTER_EMPTY)
            ->call('applyFilters')
            ->assertSet('searchQuery', 'name: Alice');

        // A key that is no filter, and a value still being typed, stay free text
        foreach (['note:', 'name:"Ali'] as $search) {
            $component->set('searchQuery', $search)->call('applySearch')
                ->assertSet('filterValues.name', '')
                ->assertSet('searchQuery', $search);
        }
    }

    public function test_no_value_typed_in_the_panel_is_valid_for_a_number_filter()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('credit')->nullable();
        });

        User::query()->delete();
        User::factory()->create(['name' => 'Has Credit'])->forceFill(['credit' => 500])->save();
        User::factory()->create(['name' => 'Has No Credit']);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'credit' => ['type' => 'number', 'label' => 'Credit', 'column' => 'credit'],
            ],
            'filterSessionKey' => 'test-mb-number-empty',
        ]);

        $component->set('filterValues.credit', BaseModelBrowser::FILTER_EMPTY)->call('applyFilters')
            ->assertHasNoErrors()
            ->assertSet('searchQuery', 'credit:');
        $component->call('loadTotalCount')->assertSet('totalCount', 1);
        $component->assertSee('Has No Credit')->assertDontSee('Has Credit');
    }

    public function test_or_column_group_matches_any_of_its_columns()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Zenon Unique', 'email' => 'someone@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'zenon@example.com']);
        User::factory()->count(5)->create();

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'filters' => [
                'user' => [
                    'type' => 'string',
                    'label' => 'User',
                    'columns' => ['name', ['column' => 'email', 'ascii_fast' => true]],
                ],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        // Matches the name of one user and the e-mail of another
        $component->set('searchQuery', 'user:zenon')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 2);

        // Non-matching value must not fall back to matching anything
        $component->set('searchQuery', 'user:nonexistentxyz')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 0);
    }

    public function test_or_column_group_is_anded_with_other_terms()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Zenon Unique', 'email' => 'zenon@example.com']);
        User::factory()->create(['name' => 'Zenon Other', 'email' => 'other@example.com']);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name', 'email' => 'Email'],
            'filters' => [
                'user' => [
                    'type' => 'string',
                    'label' => 'User',
                    'columns' => ['name', 'email'],
                ],
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        $component->set('searchQuery', 'user:zenon name:"Zenon Other"')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 1);
        $component->assertSee('Zenon Other');
    }

    public function test_number_filter_takes_a_range_an_open_range_or_a_single_value()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('credit')->default(0);
        });

        User::query()->delete();
        foreach ([500, 1000, 1500, 2500] as $credit) {
            User::factory()->create(['name' => "Credit {$credit}"])->forceFill(['credit' => $credit])->save();
        }

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'credit' => ['type' => 'number', 'label' => 'Credit', 'column' => 'credit'],
            ],
            'filterSessionKey' => 'test-mb-number-range',
        ]);

        foreach (['credit:1000..2000' => 2, 'credit:..1000' => 2, 'credit:1000..' => 3, 'credit:1000' => 1] as $search => $count) {
            $component->set('searchQuery', $search)->call('applySearch');
            $component->call('loadTotalCount')->assertSet('totalCount', $count);
        }

        // The filter panel edits the two bounds of the one value
        $component->assertSeeHtml('name="filter-credit-from"')
            ->assertSeeHtml('name="filter-credit-to"')
            ->assertSee('Credit min')
            ->assertSee('Credit max');

        // The script joining them stays inside its attribute instead of spilling onto the page
        $this->assertMatchesRegularExpression(
            '/class="mb-filters__range"\s+x-data="[^"]*fullDate\(bound, isUpper\)[^"]*"\s+wire:ignore/',
            $component->html()
        );
    }

    public function test_manual_filter_reads_the_bounds_of_a_range()
    {
        session(['test-mb-manual-range' => ['credit' => '1000..', 'created' => '2026-03-01']]);
        $model = new class
        {
            use HasModelBrowserFilters;

            protected string $modelBrowserFilterSessionKey = 'test-mb-manual-range';
        };

        $this->assertSame(['from' => '1000', 'to' => null], $model->getModelBrowserFilterRange('credit'));
        $this->assertSame(['from' => '2026-03-01', 'to' => '2026-03-01'], $model->getModelBrowserFilterRange('created'));
        $this->assertSame(['from' => null, 'to' => null], $model->getModelBrowserFilterRange('missing'));
    }

    public function test_range_filter_rejects_a_range_without_a_valid_bound()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'credit' => ['type' => 'number', 'label' => 'Credit', 'column' => 'credit'],
                'created' => ['type' => 'date', 'label' => 'Created', 'column' => 'created_at'],
            ],
            'filterSessionKey' => 'test-mb-invalid-range',
        ]);

        foreach (['credit' => ['1000..abc', '..'], 'created' => ['2026-03-01..x!', '..']] as $filter => $values) {
            foreach ($values as $value) {
                $component->set("filterValues.{$filter}", $value)->call('applyFilters')
                    ->assertHasErrors("filter-{$filter}");
            }
            $component->set("filterValues.{$filter}", '');
        }
    }

    public function test_date_filter_bound_spans_the_year_month_or_day_it_names()
    {
        Carbon::setTestNow('2026-10-15 13:00:00');
        User::query()->delete();
        foreach (['2025-12-31 23:59:59', '2026-01-01 00:00:00', '2026-09-30 12:00:00', '2026-10-01 00:00:00', '2026-10-12 08:00:00', '2026-10-31 23:30:00', '2026-11-01 00:00:00'] as $createdAt) {
            User::factory()->create(['name' => "Created {$createdAt}", 'created_at' => $createdAt]);
        }

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'created' => ['type' => 'date', 'label' => 'Created', 'column' => 'created_at'],
            ],
            'filterSessionKey' => 'test-mb-date-period',
        ]);

        $expectedCounts = [
            'created:2026' => 6,
            'created:2026..2026' => 6,
            'created:..2025' => 1,
            'created:2026-10' => 3,
            'created:2026-10..2026-10' => 3,
            'created:10.2026' => 3,
            'created:2026-09..2026-10' => 4,
            'created:2026-10-01' => 1,
            'created:2026-10-12..' => 3,
            'created:"3 days ago"' => 1,
            'created:"last month..yesterday"' => 3,
            'created:"2026-10-12 08:00:00"' => 1,
        ];
        foreach ($expectedCounts as $search => $count) {
            $component->set('searchQuery', $search)->call('applySearch');
            $component->call('loadTotalCount')->assertSet('totalCount', $count);
        }
    }

    public function test_date_filter_rejects_a_bound_that_is_not_a_date()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'created' => ['type' => 'date', 'label' => 'Created', 'column' => 'created_at'],
            ],
            'filterSessionKey' => 'test-mb-date-invalid',
        ]);

        foreach (['2026-13', '13.2026', 'soon', '2026-10..later'] as $value) {
            $component->set('filterValues.created', $value)->call('applyFilters')
                ->assertHasErrors('filter-created');
        }
    }

    public function test_date_range_over_a_relation_needs_both_bounds_on_the_same_row()
    {
        User::query()->delete();
        $straddling = User::factory()->create(['name' => 'Posted Around The Range']);
        $inside = User::factory()->create(['name' => 'Posted On The Closing Day']);
        $straddling->posts()->create(['published_at' => '2026-01-10']);
        $straddling->posts()->create(['published_at' => '2026-06-10']);
        $inside->posts()->create(['published_at' => '2026-03-31 14:30:00']);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'published' => ['type' => 'date', 'label' => 'Published', 'column' => 'published_at', 'relation' => 'posts'],
            ],
            'filterSessionKey' => 'test-mb-range-relation',
        ]);

        $component->set('searchQuery', 'published:2026-03-01..2026-03-31')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 1);
        $component->assertSee('Posted On The Closing Day')
            ->assertDontSee('Posted Around The Range');

        // An open range is met by any row
        $component->set('searchQuery', 'published:2026-03-01..')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 2);
    }

    public function test_date_range_over_an_or_group_needs_both_bounds_on_the_same_column()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Created Before Updated After', 'created_at' => '2026-01-10', 'updated_at' => '2026-06-10']);
        User::factory()->create(['name' => 'Updated In The Range', 'created_at' => '2026-01-10', 'updated_at' => '2026-03-10']);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'changed' => ['type' => 'date', 'label' => 'Changed', 'columns' => ['created_at', 'updated_at']],
            ],
            'filterSessionKey' => 'test-mb-range-group',
        ]);

        $component->set('searchQuery', 'changed:2026-03-01..2026-03-31')->call('applySearch');
        $component->call('loadTotalCount')->assertSet('totalCount', 1);
        $component->assertSee('Updated In The Range');
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

    public function test_search_query_is_initialized_from_the_q_url_parameter()
    {
        session()->put('test-mb-filters', ['name' => 'FromSession']);
        session()->put('test-mb-filters.query', 'name:FromSession');

        Livewire::withQueryParams(['q' => 'name:Alice extra'])
            ->test(BaseModelBrowser::class, [
                'model' => User::class,
                'viewAttributes' => ['name' => 'Name'],
                'filters' => [
                    'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
                ],
                'filterSessionKey' => 'test-mb-filters',
            ])
            // The URL fully describes the filter state — the session must not leak in
            ->assertSet('searchQuery', 'name:Alice extra')
            ->assertSet('filterValues.name', 'Alice');

        $this->assertSame('Alice', session('test-mb-filters.name'));
    }

    public function test_per_filter_url_parameter_takes_priority_over_the_q_parameter()
    {
        Livewire::withQueryParams(['q' => 'name:Alice', 'filter-name' => 'Bob'])
            ->test(BaseModelBrowser::class, [
                'model' => User::class,
                'viewAttributes' => ['name' => 'Name'],
                'filters' => [
                    'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name', 'url' => 'filter-name'],
                ],
                'filterSessionKey' => 'test-mb-filters',
            ])
            ->assertSet('searchQuery', 'name:Bob')
            ->assertSet('filterValues.name', 'Bob')
            ->assertDispatched('mb-clear-url-params');
    }

    public function test_session_filters_are_restored_when_no_url_parameter_is_present()
    {
        session()->put('test-mb-filters', ['name' => 'FromSession']);
        session()->put('test-mb-filters.query', 'name:FromSession');

        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ])->assertSet('searchQuery', 'name:FromSession')
            ->assertSet('filterValues.name', 'FromSession');
    }

    public function test_setting_the_search_query_alone_applies_it()
    {
        // Back/forward navigation restores the pushed `q` value by setting the
        // property directly — without any accompanying action call.
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        $component->set('skip', 40);
        $component->set('searchQuery', 'name:Alice')
            ->assertSet('filterValues.name', 'Alice')
            ->assertSet('skip', 0)
            ->assertDispatched('mb-refresh-count');

        $this->assertSame('Alice', session('test-mb-filters.name'));

        // Navigating back to an empty filter must clear the panel again
        $component->set('searchQuery', '')
            ->assertSet('filterValues.name', '');

        $this->assertNull(session('test-mb-filters.name'));
    }

    public function test_count_refresh_is_dispatched_once_per_request()
    {
        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-filters',
        ]);

        // The deferred searchQuery update and the applySearch call it submits
        // with both change the filters — the count island must only reload once.
        $component->set('searchQuery', 'name:Alice')->call('applySearch');

        $dispatched = array_filter(
            $component->effects['dispatches'] ?? [],
            fn ($dispatch) => $dispatch['name'] === 'mb-refresh-count'
        );

        $this->assertCount(1, $dispatched);
    }

    public function test_stats_summarize_a_numeric_column()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('score')->nullable();
        });

        User::query()->delete();
        foreach ([10, 30, 0, null] as $score) {
            User::factory()->create()->forceFill(['score' => $score])->save();
        }

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['score' => 'Score'],
            'statsAttributes' => ['score'],
        ]);

        $component->call('loadTotalStats');
        $stats = $component->get('stats')['score'];

        $this->assertTrue($stats['numeric']);
        $this->assertSame(4, $stats['count']);
        // The zero and the null both fall out of the non-zero count
        $this->assertSame(2, $stats['countnz']);
        $this->assertSame(40.0, $stats['sum']);
        $this->assertSame(10.0, $stats['avg']);
        $this->assertSame(20.0, $stats['avgnz']);
        $this->assertSame(0.0, $stats['min']);
        $this->assertSame(10.0, $stats['minnz']);
        $this->assertSame(30.0, $stats['max']);
    }

    public function test_stats_of_a_text_column_are_only_its_row_counts()
    {
        User::query()->delete();
        User::factory()->create(['name' => 'Named Person']);
        User::factory()->create()->forceFill(['name' => ''])->save();

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'statsAttributes' => ['name'],
        ]);

        $component->call('loadTotalStats');
        $stats = $component->get('stats')['name'];

        $this->assertFalse($stats['numeric']);
        $this->assertSame(2, $stats['count']);
        $this->assertSame(1, $stats['countnz']);
        foreach (['sum', 'avg', 'avgnz', 'min', 'minnz', 'max'] as $key) {
            $this->assertNull($stats[$key], $key);
        }

        // Only COUNT and COUNTNZ are worth listing in the menu
        $this->assertSame(
            ['count', 'countnz'],
            array_column($component->instance()->columnStatsRows('name'), 'key'),
        );
    }

    public function test_stats_are_not_computed_above_the_limit()
    {
        User::query()->delete();
        User::factory()->count(5)->create();

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'statsAttributes' => ['name'],
            'statsLimit' => 3,
        ]);

        $component->call('loadTotalStats')
            ->assertSet('stats', null)
            ->assertSet('statsOverLimit', true);
    }

    public function test_stats_keep_the_relations_the_summary_method_eager_loads()
    {
        User::query()->delete();
        foreach ([2, 0, 1, 3, 1] as $posts) {
            User::factory()->create()->posts()->createMany(array_fill(0, $posts, ['title' => 'Post']));
        }

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class . '@withPosts',
            'viewAttributes' => ['post_count' => 'Posts'],
            'statsAttributes' => ['post_count'],
        ]);

        DB::enableQueryLog();
        $component->call('loadTotalStats');
        $postQueries = array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], '"posts"'));

        $this->assertSame(7.0, $component->get('stats')['post_count']['sum']);
        $this->assertLessThan(User::count(), count($postQueries));
    }

    public function test_changing_the_filters_discards_the_loaded_stats()
    {
        User::query()->delete();
        User::factory()->count(3)->create();

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'statsAttributes' => ['name'],
            'filters' => [
                'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name'],
            ],
            'filterSessionKey' => 'test-mb-stats-filters',
        ]);

        $component->call('loadTotalStats');
        $this->assertNotNull($component->get('stats'));

        $component->set('searchQuery', 'name:Nobody')->call('applySearch')
            ->assertSet('stats', null);

        $dispatched = array_filter(
            $component->effects['dispatches'] ?? [],
            fn ($dispatch) => $dispatch['name'] === 'mb-refresh-stats'
        );

        $this->assertCount(1, $dispatched);
    }

    public function test_nothing_is_summarized_without_stats_attributes()
    {
        User::query()->delete();
        User::factory()->count(3)->create();

        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->call('loadTotalStats')
            ->assertSet('stats', null)
            ->assertSet('statsOverLimit', false);
    }

    public function test_stats_are_only_offered_for_view_attributes()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
            'statsAttributes' => ['name', 'email'],
        ])->assertSet('statsAttributes', ['name']);
    }

    public function test_renders_copy_page_button()
    {
        Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => ['name' => 'Name'],
        ])->assertSee(__('model-browser::global.copy-page.label'))
            ->assertSeeHtml('copyPage()');
    }
}
