<?php

namespace Internetguru\ModelBrowser;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Internetguru\ModelBrowser\Components\TableModelBrowser;
use Livewire\Livewire;

class ModelBrowserServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'model-browser');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'model-browser');

        Livewire::component('base-model-browser', BaseModelBrowser::class);
        Livewire::component('table-model-browser', TableModelBrowser::class);

        $this->registerQueryMacros();

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/model-browser'),
        ], 'views');
        $this->publishes([
            __DIR__ . '/../lang' => resource_path('lang/vendor/model-browser'),
        ], 'translations');
    }

    /**
     * Register query builder macros.
     */
    protected function registerQueryMacros(): void
    {
        $this->registerSqliteUnaccent();

        /**
         * Accent-insensitive (and case-insensitive) LIKE filter.
         * Supports SQLite (via custom unaccent function) and MySQL/MariaDB (via collation).
         *
         * Usage: $query->whereLikeUnaccented('name', $value)
         */
        Builder::macro('whereLikeUnaccented', function (string $column, string $value): Builder {
            /** @var Builder $this */
            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                return $this->whereRaw(
                    "unaccent({$column}) LIKE unaccent(?)",
                    ['%' . $value . '%']
                );
            }

            // MySQL / MariaDB
            return $this->whereRaw(
                "{$column} COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci",
                ['%' . $value . '%']
            );
        });
    }

    /**
     * Register a custom `unaccent` function for SQLite connections.
     */
    protected function registerSqliteUnaccent(): void
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if ($driver !== 'sqlite') {
            return;
        }

        $pdo = $this->app['db']->connection()->getPdo();
        $pdo->sqliteCreateFunction('unaccent', function (?string $string): string {
            if ($string === null) {
                return '';
            }

            if (class_exists(\Transliterator::class)) {
                $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower');

                return $transliterator ? $transliterator->transliterate($string) : mb_strtolower($string);
            }

            // Fallback: manual Czech/Central European diacritics mapping
            return strtr(mb_strtolower($string), [
                'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
                'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
                'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
                'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
                'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
                'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
                'ñ' => 'n', 'ç' => 'c', 'ł' => 'l', 'ő' => 'o', 'ű' => 'u',
                'ø' => 'o', 'å' => 'a', 'æ' => 'ae',
            ]);
        }, 1);
    }
}
