<?php

namespace Tests;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
        $this->createPostsTable();

        // Load your package's migrations if needed
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Create test data
        $this->setUpTestData();
    }

    /**
     * A to-many relation off the users table, for exercising relation filters.
     */
    protected function createPostsTable(): void
    {
        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title')->nullable();
        });
    }

    protected function setUpTestData()
    {
        // remove all users
        User::query()->delete();
        // create 10 users
        User::factory()->count(10)->create();
    }
}
