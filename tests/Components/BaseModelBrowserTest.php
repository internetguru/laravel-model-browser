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
        // skip
        $this->markTestSkipped('Need to fix this test');

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
        ]);

        $response = $component->call('downloadCsv');

        $response->assertHeader('Content-Type', 'text/csv');
        $response->assertHeader('Content-Disposition', 'attachment; filename=data.csv');
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
        // count 10 users
        $component->assertSee('1–10 of 10');

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

    public function test_view_with_highlighted_matches()
    {
        // skip
        $this->markTestSkipped('Need to fix this test as well');

        // mock url setting filter
        $firstUser = User::first();
        $this->app['request']->merge(['filter' => $firstUser->name]);

        $component = Livewire::test(BaseModelBrowser::class, [
            'model' => User::class,
            'viewAttributes' => [
                'name' => 'Name',
            ],
        ]);

        $component->assertSeeHtml("<mark>{$firstUser->name}</mark>");
    }
}
