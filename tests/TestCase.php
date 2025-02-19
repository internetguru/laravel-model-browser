<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InternetGuru\LaravelCommon\CommonServiceProvider;
use Internetguru\ModelBrowser\ModelBrowserServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            ModelBrowserServiceProvider::class,
            CommonServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Optionally, set up your environment here
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);

        // Load your package's migrations if needed
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Create test data
        $this->setUpTestData();
    }

    protected function setUpTestData()
    {
        // remove all users
        \App\Models\User::query()->delete();
        // create 10 users
        \App\Models\User::factory()->count(10)->create();
    }
}
