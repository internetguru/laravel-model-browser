<?php

namespace Internetguru\ModelBrowser;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Internetguru\ModelBrowser\Components\TableModelBrowser;
use Internetguru\ModelBrowser\Http\Controllers\CsvDownloadController;
use Internetguru\ModelBrowser\Support\QueryDeadline;
use Livewire\Livewire;
use PDO;

class ModelBrowserServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/model-browser.php', 'model-browser');
    }

    /**
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'model-browser');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'model-browser');

        Livewire::component('base-model-browser', BaseModelBrowser::class);
        Livewire::component('table-model-browser', TableModelBrowser::class);

        $this->registerQueryMacros();
        $this->registerSanitizeTypes();

        // Direct CSV streaming endpoint — bypasses Livewire's buffered,
        // base64-encoded file downloads (see CsvDownloadController).
        Route::post('model-browser/download-csv', CsvDownloadController::class)
            ->middleware('web')
            ->name('model-browser.download-csv');

        // The download-started cookie is read back by the CSV button's JS,
        // so it must stay unencrypted.
        EncryptCookies::except('mb_csv_download');

        $this->publishes([
            __DIR__ . '/../config/model-browser.php' => config_path('model-browser.php'),
        ], 'config');
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
    /**
     * Declare the sanitization type of the browser's client-writable state.
     *
     * internetguru/laravel-common normalizes input by what a value is, and
     * reports anything it cannot type. Only four of this component's properties
     * are writable by the client - the other sixteen are #[Locked] and are
     * skipped for that reason - and `searchQuery` is already covered by the
     * `search_query` default. Individual filter columns are declared per
     * attribute in BaseModelBrowser::validateFilterValue(), where their
     * configured type is known.
     *
     * Entries the application has already set win.
     */
    protected function registerSanitizeTypes(): void
    {
        $types = config('ig-common.sanitize.types');

        if (! is_array($types)) {
            return;
        }

        config()->set('ig-common.sanitize.types', [
            'filter_values' => 'search',
            'sort_column' => 'code',
            'sort_direction' => 'code',
            ...$types,
        ]);
    }

    protected function registerQueryMacros(): void
    {
        $this->registerSqliteUnaccent();

        /**
         * Accent-insensitive (and case-insensitive) LIKE filter.
         * Supports SQLite (via custom unaccent function) and MySQL/MariaDB (via collation).
         *
         * Usage: $query->whereLikeUnaccented('name', $value)
         *
         * Pass $asciiFast=true when the column is known to store only ASCII
         * (e-mail, login, slug, …). On SQLite this skips the unaccent() PHP UDF
         * — which is invoked per row scanned and is the main filter-cost driver.
         */
        Builder::macro('whereLikeUnaccented', function (string|Expression $column, string $value, bool $asciiFast = false): Builder {
            /** @var Builder $this */
            // A name is quoted; an expression passed as DB::raw() goes in as it is
            $sql = $this->getQuery()->getGrammar()->wrap($column);

            // If the search value contains accented characters, do an exact (accent-sensitive) match
            $hasAccents = $value !== \Normalizer::normalize($value, \Normalizer::FORM_D)
                || (bool) preg_match('/[^\x00-\x7F]/', $value);

            // @phpstan-ignore method.notFound (an Eloquent connection is always an Illuminate\Database\Connection)
            $driver = $this->getConnection()->getDriverName();

            if ($hasAccents) {
                // SQLite's LIKE ignores the case of ASCII letters only, so `Š` would not match `š`
                if ($driver === 'sqlite' && ! $asciiFast) {
                    return $this->whereRaw(
                        // @phpstan-ignore argument.type (the column is quoted by the grammar above)
                        "unicode_lower({$sql}) LIKE ?",
                        ['%' . \Normalizer::normalize(mb_strtolower($value), \Normalizer::FORM_C) . '%']
                    );
                }

                return $this->where($column, 'LIKE', '%' . $value . '%');
            }

            if ($driver === 'sqlite') {
                if ($asciiFast) {
                    return $this->whereRaw(
                        // @phpstan-ignore argument.type (the column is quoted by the grammar above)
                        "LOWER({$sql}) LIKE LOWER(?)",
                        ['%' . $value . '%']
                    );
                }

                return $this->whereRaw(
                    // @phpstan-ignore argument.type (the column is quoted by the grammar above)
                    "unaccent({$sql}) LIKE ?",
                    ['%' . $value . '%']
                );
            }

            // MySQL / MariaDB
            // CONVERT(... USING utf8mb4) gives the placeholder an explicit charset before
            // COLLATE is applied — some MySQL/PDO configurations otherwise infer the bound
            // parameter's charset as 'binary', which is incompatible with utf8mb4_unicode_ci.
            return $this->whereRaw(
                // @phpstan-ignore argument.type (the column is quoted by the grammar above)
                "{$sql} COLLATE utf8mb4_unicode_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci",
                ['%' . $value . '%']
            );
        });
    }

    /**
     * Register custom `unaccent`, `unicode_lower` and `query_deadline` functions for SQLite connections.
     * Deferred until the connection is actually used.
     */
    protected function registerSqliteUnaccent(): void
    {
        $this->app->make('events')->listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            $connection = $event->connection;
            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }

            // The PDO is still a resolver here; asking for it would open the database, which may not even exist yet
            $connection->setPdo($this->withSqliteFunctions($connection->getRawPdo()));
            $connection->setReadPdo($this->withSqliteFunctions($connection->getRawReadPdo()));
        });
    }

    /**
     * Add the custom SQLite functions to a PDO, or to the one a resolver returns once it is called.
     */
    protected function withSqliteFunctions(Closure|PDO|null $pdo): Closure|PDO|null
    {
        if ($pdo instanceof Closure) {
            return fn (): PDO => $this->addSqliteFunctions($pdo());
        }
        if ($pdo instanceof PDO) {
            $this->addSqliteFunctions($pdo);
        }

        return $pdo;
    }

    protected function addSqliteFunctions(PDO $pdo): PDO
    {
        // Created once: the function runs for every row scanned, and creating it costs far more than using it
        $transliterator = class_exists(\Transliterator::class)
            ? \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower')
            : null;
        $pdo->sqliteCreateFunction('unaccent', function (?string $string) use ($transliterator): string {
            QueryDeadline::check();

            if ($string === null) {
                return '';
            }

            if ($transliterator) {
                return (string) $transliterator->transliterate($string);
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
        $pdo->sqliteCreateFunction('unicode_lower', function (?string $string): string {
            QueryDeadline::check();

            return $string === null ? '' : (string) \Normalizer::normalize(mb_strtolower($string), \Normalizer::FORM_C);
        }, 1);
        // Takes a value that changes per row, so SQLite calls it for every row instead of once as a constant
        $pdo->sqliteCreateFunction('query_deadline', function (mixed $value): int {
            QueryDeadline::check();

            return 1;
        }, 1);

        return $pdo;
    }
}
