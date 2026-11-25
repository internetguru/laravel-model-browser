<?php

namespace Tests;

use App\Models\Post;
use Illuminate\Support\Facades\DB;

class WhereLikeUnaccentedTest extends TestCase
{
    protected function setUpTestData()
    {
        Post::create(['user_id' => 1, 'title' => 'Žluťoučký kůň']);
        Post::create(['user_id' => 1, 'title' => 'Plain title']);
    }

    public function test_a_qualified_column_is_quoted()
    {
        $query = Post::query()->whereLikeUnaccented('posts.title', 'zlutoucky');

        $this->assertStringContainsString('unaccent("posts"."title")', $query->toSql());
        $this->assertSame(['Žluťoučký kůň'], $query->pluck('title')->all());
    }

    public function test_an_accented_value_matches_through_a_quoted_column()
    {
        $query = Post::query()->whereLikeUnaccented('posts.title', 'ŽLUŤOUČKÝ');

        $this->assertStringContainsString('unicode_lower("posts"."title")', $query->toSql());
        $this->assertSame(['Žluťoučký kůň'], $query->pluck('title')->all());
    }

    public function test_an_ascii_fast_search_quotes_the_column()
    {
        $query = Post::query()->whereLikeUnaccented('title', 'PLAIN', asciiFast: true);

        $this->assertStringContainsString('LOWER("title")', $query->toSql());
        $this->assertSame(['Plain title'], $query->pluck('title')->all());
    }

    public function test_an_expression_goes_in_unquoted()
    {
        $query = Post::query()->whereLikeUnaccented(DB::raw("title || ' extra'"), 'title extra');

        $this->assertStringContainsString("unaccent(title || ' extra')", $query->toSql());
        $this->assertSame(['Plain title'], $query->pluck('title')->all());
    }

    public function test_a_sqlite_connection_is_not_opened_until_it_is_used()
    {
        config(['database.connections.missing' => ['driver' => 'sqlite', 'database' => '/nonexistent/database.sqlite', 'prefix' => '']]);

        $this->assertInstanceOf(\Closure::class, DB::connection('missing')->getRawPdo());
    }
}
