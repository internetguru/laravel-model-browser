# Laravel Model Browser

A Laravel package to browse models and show them in cards, tables, etc.

| Branch  | Status | Code Coverage |
| :------------- | :------------- | :------------- |
| Main | ![tests](https://github.com/internetguru/laravel-model-browser/actions/workflows/test.yml/badge.svg?branch=main) | ![coverage](https://raw.githubusercontent.com/internetguru/laravel-model-browser/refs/heads/badges/main-coverage.svg) |

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13
- Livewire 4

> **Livewire 3 → 4:** As of version 4.3, this package requires Livewire 4. Support for Livewire 3 (and Laravel 9/10) has been dropped. The components are class-based and registered by the package's service provider, so no application changes are needed beyond upgrading Livewire itself.

## Installation

1. Install the package via Composer:

    ```sh
    # First time installation
    composer require internetguru/laravel-model-browser
    # For updating the package
    composer update internetguru/laravel-model-browser
    ```

2. Optionally publish the config, views, and translations:

    ```sh
    php artisan vendor:publish --tag=config --provider="Internetguru\ModelBrowser\ModelBrowserServiceProvider"
    php artisan vendor:publish --tag=views --provider="Internetguru\ModelBrowser\ModelBrowserServiceProvider"
    php artisan vendor:publish --tag=translations --provider="Internetguru\ModelBrowser\ModelBrowserServiceProvider"

    # If you want to publish everything, you can use the `--provider` option:
    php artisan vendor:publish --provider="Internetguru\ModelBrowser\ModelBrowserServiceProvider"
    ```

## Run Tests Locally

To run the tests manually, you can use the following command:

```sh
./test.sh
```


## Basic Usage

Show the model browser in your views:

```html
<!-- Base view (cards) -->
<livewire:base-model-browser model="App\Models\User" />

<!-- Table view -->
<livewire:table-model-browser model="App\Models\User" />
```

If no `viewAttributes` are provided, the model's fillable attributes are used by default.

## Component Parameters

Both `BaseModelBrowser` and `TableModelBrowser` accept the following parameters:

### `model` (required)

The Eloquent model class. Optionally specify a method (scope) to call on the model:

```php
model="App\Models\User"
model="App\Models\User@summary"
```

### `viewAttributes`

Attributes displayed as columns/cards, mapped to their labels:

```php
:viewAttributes="[
    'created_at' => __('summary.created_at'),
    'name' => __('summary.name'),
    'email' => __('summary.email'),
]"
```

### `exportAttributes`

Attributes included in the CSV export, mapped to their labels. They are hidden in the table and follow the `viewAttributes` columns, in the order given here. An entry whose key is also a view attribute is exported once, in its `exportAttributes` position — so a hidden column can be exported next to the visible one it belongs with:

```php
:viewAttributes="['created_at' => __('summary.created_at'), 'customer.name' => __('summary.ordered_by')]"
:exportAttributes="[
    'customer.name' => __('summary.ordered_by'),
    'customer.email' => __('summary.ordered_by_email'),
]"
```

The export above has three columns: `created_at`, `customer.name`, `customer.email`.

Only attributes with a single value per row belong here: own columns, or dot paths over to-one relations. A to-many relation has no single value to put in a cell. `formats` are irrelevant for these columns (nothing renders them); `rawFormats` apply as usual. Eager-load the relations they reach through via `with` to avoid an N+1 query per exported row.

### `formats`

Formatting functions for attribute values. Each function receives `($value, $item)` and returns the formatted output (HTML is allowed). Values are passed as global function name strings:

```php
:formats="[
    'created_at' => 'formatDateTime',
    'price' => 'formatCurrency',
    'symbol' => 'formatOrderSymbol',
    'payment_type' => 'formatTransactionPaymentType',
]"
```

Define the formatting functions as global helpers, e.g. in a `helpers.php` file:

```php
function formatDateTime($value, $item)
{
    return \Carbon\Carbon::parse($value)->format('d.m.Y H:i');
}

function formatCurrency($value, $item)
{
    return number_format($value / 100, 2) . ' CZK';
}
```

### `alignments`

Column alignment settings (`start`, `end`, or `center`). Numeric values default to `end`, others to `start`:

```php
:alignments="[
    'created_at' => 'start',
    'amount' => 'end',
    'is_active' => 'center',
]"
```

### `defaultSortColumn` / `defaultSortDirection`

Default sort when user hasn't selected one:

```php
defaultSortColumn="created_at"
defaultSortDirection="desc"
```

### `enableSort`

Enable/disable interactive column sorting (default: `true`):

```php
:enableSort="false"
```

### `filters` / `filterSessionKey`

See the [Filters](#filters) section below. When using `filters`, `filterSessionKey` is required.

### `refreshInterval`

Auto-refresh interval in seconds. When set, the component polls the server and re-renders with fresh data (including total count). Default: `0` (disabled):

```php
:refreshInterval="10"
```

### `exportLimit`

Maximum number of rows a CSV export may contain. When the current (filtered) result count exceeds the limit, the download button is disabled and the export endpoint refuses the request. Defaults to the `model-browser.export_limit` config value (1500). Set to `0` for unlimited:

```php
:exportLimit="2000"
```

### `statsAttributes` / `statsLimit`

Columns that are summarized. Their header offers the statistics menu — an icon opening `SUM`, `AVG`, `MIN`, `MAX`, `COUNT`, `AVGNZ`, `MINNZ` and `COUNTNZ`, with a button copying the lot to the clipboard. See [Column Statistics](#column-statistics):

```php
:statsAttributes="['price', 'credit']"
:statsLimit="1000"
```

`statsLimit` is the largest result count the statistics are computed for. Summarizing walks the whole filtered result set, so above it nothing is computed and the menu asks for narrower filters instead. Defaults to the `model-browser.stats_limit` config value (5000); set to `0` for unlimited.

### TableModelBrowser-only Parameters

#### `lightDarkStep`

Controls alternating row shading in the table (default: `1`):

```php
:lightDarkStep="2"
```

#### `columnWidths`

Custom CSS grid column widths per attribute. Defaults to `minmax(4em, 1fr)`:

```php
:columnWidths="[
    'name' => 'minmax(8em, 2fr)',
    'email' => 'minmax(10em, 2fr)',
    'is_active' => '6em',
]"
```

## Filters

The filter system provides a search bar with Gmail-style query syntax and an expandable filter panel. The active filter lives in the `q` URL query parameter (see [Filter State in the URL](#filter-state-in-the-url)), is persisted in the session, and can additionally be initialized from per-filter URL query parameters.

### Configuration

Pass an associative array to the `filters` parameter. Each key is a filter name in kebab case (e.g. `created-by`), and each value is a config array:

```php
:filters="[
    'created' => [
        'type' => 'date',
        'label' => 'Created',
        'column' => 'created_at',
        'timezone' => 'Europe/Prague',
    ],
    'symbol' => [
        'type' => 'string',
        'label' => 'Symbol',
        'column' => 'symbol',
        'rules' => 'nullable|string|max:20',
    ],
    'voucher' => [
        'type' => 'string',
        'label' => 'Voucher',
        'column' => 'ulid',
        'relation' => 'charges.voucher',
        'rules' => 'nullable|string|max:32',
        'url' => 'voucher',
    ],
    'price' => [
        'type' => 'number',
        'label' => 'Price',
    ],
    'name' => [
        'type' => 'string',
        'label' => 'Customer',
        'column' => 'name',
        'relation' => 'customer',
    ],
]"
filterSessionKey="order-browser-filters"
```

Note that `price` has no `column` key — it is not auto-applied and must be handled manually in the model scope (see [HasModelBrowserFilters Trait](#hasmodelbrowserfilters-trait)).

### Filter Config Keys

| Key | Description |
|---|---|
| `type` | Filter type: `string`, `number`, `date`, `options`, `checkbox` (default: `string`). `number` and `date` take a range (see [Ranges](#ranges)). **Note:** `checkbox` renders a single on/off box whose value is `1` when checked and empty when not — with a `column` it matches `column = 1`, without one it is left to the model scope (see [HasModelBrowserFilters Trait](#hasmodelbrowserfilters-trait)). |
| `label` | Display label in the filter panel |
| `column` | Database column name for auto-apply. **When set**, the filter is automatically applied to the query. **When omitted** (and no `columns`), the filter is NOT auto-applied — use `HasModelBrowserFilters` trait for manual access. |
| `columns` | OR group — a list of columns matched with `OR` instead of a single `column` (see [OR Column Groups](#or-column-groups)) |
| `relation` | Eloquent relation name — wraps the filter in `whereHas()`. Supports dot-notation for nested relations. |
| `options` | Array of options for the `options` type (e.g. `['value' => 'Label']`) |
| `rules` | Custom Laravel validation rules (overrides default type-based rules) |
| `url` | URL query parameter name to initialize the filter from (takes priority over session) |
| `timezone` | Timezone for date filters — the parsed date value is shifted via `Carbon::shiftTimezone($tz)` (e.g. `'Europe/Prague'`) |

### OR Column Groups

A single filter can match against several columns at once. Use `columns` instead of `column` — the filter's value matches when **any** of the listed columns matches, while the filter as a whole is still AND'd with every other term.

The typical case is collapsing a name + e-mail pair into one input:

```php
'customer' => [
    'type' => 'string',
    'label' => 'Customer',
    'relation' => 'customer',
    'rules' => 'nullable|string|max:60',
    'columns' => ['name', ['column' => 'email', 'ascii_fast' => true]],
],
```

`customer:novak` then matches customers whose **name** or **e-mail** contains `novak`, and the filter panel shows one input instead of two.

Each `columns` entry is either a plain column name or an array overriding `column`, `relation`, `preprocessor`, `ascii_fast`, `type` or `timezone` **for that column only**. Anything not overridden falls back to the filter's own config, so the shared `relation` above applies to both columns. Columns may also live in different relations:

```php
'party' => [
    'type' => 'string',
    'label' => 'Party',
    'columns' => [
        ['column' => 'name', 'relation' => 'customer'],
        ['column' => 'name', 'relation' => 'author'],
    ],
],
```

All columns of an OR group also take part in free-text search, exactly as separate `column` filters would.

### Ranges

A `number` or `date` filter takes a range, its bounds separated by `..`:

```
price:1000..2000    # 1000 to 2000, both included
price:..1000        # up to 1000
price:1000..        # 1000 and more
price:1000          # exactly 1000, the range 1000..1000
created:2026-03-01..2026-03-31
```

A date bound stands for the whole period it names. The lower bound is the period's first moment and the upper bound its last, so a single value covers the whole period:

```
created:2026                    # the whole year
created:2026-10                 # the whole month, also written 10.2026
created:2026-09..2026-10        # September and October
created:2026-10-15              # the whole day, also written 15.10.2026
created:"3 days ago"            # that whole day
created:"last month..yesterday" # from the first day of last month to the end of yesterday
created:"2026-10-15 08:00"      # a bound with a time is that moment
```

Relative bounds are anything `Carbon::parse()` reads (in English), and they span the unit they name: day, week, month, year, hour or minute; `today`, `yesterday` and `tomorrow` are days. Dates are read in the filter's `timezone`. Each bound is validated on its own against the filter's rules, and a date bound that is not a date is an error.

Both bounds must hold for the same related row, and for the same column of an OR group: with `published:2026-03-01..2026-03-31` over `posts.published_at`, a user with one post before the range and another after it is not listed.

In the filter panel, a range filter shows two inputs, one for each bound, which are joined into the one value. A date input shows the first or the last day of a bound's period; a bound left untouched keeps what was written, such as `2026-10` or `3 days ago`.

### Search Query Syntax

The search bar supports Gmail-style syntax:

- **Free text**: `john` — searches across all `string`-type filter columns (with `column` set)
- **Specific filter**: `name:john` — applies to the `name` filter
- **Quoted values**: `name:"John Doe"` — for values containing spaces
- **No value at all**: `name:` — the rows the filter finds nothing on
- **Range**: `price:1000..2000` — for `number` and `date` filters (see [Ranges](#ranges))
- **Combined**: `name:john created:2025-01-01..` — all terms must match (AND)

#### Searching for rows with no value

A bare `attribute:`, followed by a space or the end of the query, matches the rows where the
filter has nothing to match on: a column that is `NULL` or empty, and, for a filter over a
`relation`, a row whose relation is missing altogether. With an OR group of `columns`, a row
qualifies only when *none* of them carries a value. `attribute:""` reads the same, and the
query built from the filter panel writes the bare form.

```
ordered-by:       # orders nobody is named on
paid: novak       # unpaid orders, and the free text novak
```

A bare key that is no configured filter, such as `note:`, stays free text, and so does an
unfinished `name:"Jo`. In the filter panel, type `""` into a text field to search for no value.

### Auto-applied vs Manual Filters

Filters with a `column` key are **auto-applied** to the Eloquent query. Filters without `column` are stored in session but require manual application — useful for custom logic in model scopes:

```php
// Auto-applied filter (no manual code needed):
'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name']

// Manual filter (applied in your model scope via HasModelBrowserFilters):
'price' => ['type' => 'number', 'label' => 'Price']
```

Typical reasons to omit `column` and handle filtering manually:
- The filter operates on a computed/aggregate value (e.g. sum of related records)
- The filter needs custom OR logic across multiple relations
- The filter requires raw SQL expressions

### HasModelBrowserFilters Trait

Use this trait in your Eloquent model to access filter values from session for manual filtering. The `$modelBrowserFilterSessionKey` must match the `filterSessionKey` passed to the component.

```php
use Internetguru\ModelBrowser\Traits\HasModelBrowserFilters;

class Order extends Model
{
    use HasModelBrowserFilters;

    protected string $modelBrowserFilterSessionKey = 'order_filter';

    public static function summary()
    {
        $query = static::with(['customer', 'payment', 'charges']);
        $price = (new static)->getModelBrowserFilterRange('price');

        // Manual filter: price is a computed sum of related charges
        if ($price['from'] !== null) {
            $query->whereRaw(
                '(SELECT SUM(amount) FROM charges WHERE charges.order_id = orders.id) >= ?',
                [$price['from'] * 100]
            );
        }
        if ($price['to'] !== null) {
            $query->whereRaw(
                '(SELECT SUM(amount) FROM charges WHERE charges.order_id = orders.id) <= ?',
                [$price['to'] * 100]
            );
        }

        return $query;
    }
}
```

Available methods:

- `getModelBrowserFilters()` — returns a `Collection` of active filter values
- `getModelBrowserFilter(string $key, mixed $default = null)` — get a specific filter value
- `getModelBrowserFilterRange(string $key)` — the bounds of a `number` or `date` filter as `['from' => ?string, 'to' => ?string]`, `null` for an open one; turn a date bound into its first and last moment with `BaseModelBrowser::parseDatePeriod($bound, $timezone)`
- `hasModelBrowserFilter(string $key)` — check if a filter is set
- `hasModelBrowserFilters()` — check if any filters are active

### URL-based Filter Initialization

Filters with a `url` key can be initialized from URL query parameters. When any URL filter is present, session-stored filters are ignored and URL values take priority:

```php
'status' => [
    'type' => 'options',
    'label' => 'Status',
    'column' => 'status',
    'url' => 'filter-status',
    'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
]
```

Link: `/orders?filter-status=active`

The URL parameters are automatically cleaned from the browser address bar after initialization — the resulting filter is then carried by the `q` parameter like any other.

### Filter State in the URL

The whole search query is mirrored into the `q` query parameter, so the current filter is part of the URL:

```
/orders?q=status%3Aactive+name%3A%22John+Doe%22
```

- The URL is shareable and bookmarkable — opening it applies exactly that filter.
- Every filter change pushes a browser history entry, so **back/forward moves between filter states**.
- On a plain page load without `q`, the filter is restored from the session and the URL is updated to match.

Priority on mount is: per-filter `url` parameters > `q` > session. When `q` is present it fully describes the filter state, so session values are never merged into it — otherwise navigating back would resurrect cleared filters.

## Full Example

Below is a complete example of an order browser with auto-applied and manual filters:

```html
<livewire:table-model-browser
    model="App\Models\Order@summary"
    filterSessionKey="order_filter"
    :viewAttributes="[
        'created_at' => __('summary.created_at'),
        'symbol' => __('summary.symbol'),
        'price' => __('summary.total'),
        'customer.name' => __('summary.customer'),
        'customer.email' => __('summary.email'),
        'payment_accepted_at' => __('summary.paid_at'),
        'payment_type' => __('summary.payment_type'),
    ]"
    :formats="[
        'symbol' => 'formatOrderSymbol',
        'created_at' => 'formatDateTime',
        'price' => 'formatCurrency',
        'payment_accepted_at' => 'formatDateTime',
        'payment_type' => 'formatTransactionPaymentType',
    ]"
    :filters="[
        'created' => [
            'type' => 'date',
            'label' => __('summary.created'),
            'column' => 'created_at',
        ],
        'symbol' => [
            'type' => 'string',
            'label' => __('summary.symbol_filter'),
            'rules' => 'nullable|string|max:20',
            'column' => 'symbol',
        ],
        'voucher' => [
            'type' => 'string',
            'label' => __('summary.voucher_filter'),
            'rules' => 'nullable|string|max:32',
            'url' => 'voucher',
            'column' => 'ulid',
            'relation' => 'charges.voucher',
        ],
        'price' => [
            'type' => 'number',
            'label' => __('summary.price'),
        ],
        'name' => [
            'type' => 'string',
            'label' => __('summary.name_filter'),
            'rules' => 'nullable|string|max:30',
            'column' => 'name',
            'relation' => 'customer',
        ],
        'email' => [
            'type' => 'string',
            'label' => __('summary.email_filter'),
            'rules' => 'nullable|string|max:30',
            'column' => 'email',
            'relation' => 'customer',
        ],
    ]"
    :columnWidths="[
        'created_at' => 'minmax(7em, 1.2fr)',
        'symbol' => 'minmax(8em, 0.5fr)',
        'price' => 'minmax(max-content, max-content)',
        'customer.name' => 'minmax(7em, 1.2fr)',
        'customer.email' => 'minmax(7em, 1.8fr)',
        'payment_accepted_at' => 'minmax(7em, 1.2fr)',
        'payment_type' => 'minmax(7em, 1fr)',
    ]"
    defaultSortColumn="created_at"
    defaultSortDirection="desc"
    :enableSort="false"
/>
```

In this example:
- `created`, `symbol`, `voucher`, `name`, `email` have `column` set → **auto-applied** to the query
- `price` has no `column` → a **manual filter** handled in `Order::summary()` via `HasModelBrowserFilters`
- `voucher` uses `relation` with dot-notation (`charges.voucher`) for nested `whereHas()` and `url` for URL initialization

## Features

- **Pagination** — One line above the table, reading `1–20 of 176` followed by the previous/next buttons, which move by `perPage` (`PER_PAGE_DEFAULT`, 20). A **Load more results** button under the table shows another `PER_PAGE_STEP` (20) rows below the ones already there, up to `PER_PAGE_MAX` in total. Only the current page grows: `perPage` is untouched, so the arrows always load a default page and drop the extra rows on the way, and nothing about it is remembered for the next visit. The extra rows are counted in `extraRows` and reset by paging and by any filter change.
- **Auto-refresh** — Optional periodic data refresh via `refreshInterval` parameter.
- **Sorting** — Click column headers to sort ascending/descending or reset. Supports default sort column and direction.
- **CSV Export** — Download the current filtered and sorted data as a CSV file. Exports are capped at `exportLimit` rows (per-instance parameter, defaults to the `model-browser.export_limit` config value of 1500; `0` disables the cap) — when the current result count exceeds it, the download button is disabled and the export endpoint refuses the request.
- **Column statistics** — Summarized columns carry a statistics menu in their header, loaded inside its own Livewire 4 [island](https://livewire.laravel.com/docs/4.x/islands) so the data query is never re-run for it. See [Column Statistics](#column-statistics).
- **Fullscreen** — Toggle fullscreen mode for the table view.
- **Copy page** — Copy the rows of the *current page only* to the clipboard, as plain text (TSV) and as an HTML table, ready to paste into a spreadsheet. Cells are copied as their raw `data-raw` values (the same values the CSV export uses), not the `formats`-rendered display text.
- **Deferred count** — The total result count is the `of 176` half of the pagination line and is loaded inside a dedicated Livewire 4 [island](https://livewire.laravel.com/docs/4.x/islands), passed into the pagination component as its `count` slot. The table renders immediately from the `rows()` computed property; the count fills in (and refreshes on filter changes) without ever re-running the data query.

## Column Statistics

The columns named in `statsAttributes` carry an icon in their header opening a menu of statistics. The icon is greyed out and disabled until the statistics arrive. It sits in a box of a fixed size, because FontAwesome replaces its `<i>` with an `<svg>` only after the page has been laid out. The menu is kept within the visible part of the screen:

| Statistic | |
| :--- | :--- |
| `SUM` | Total of the column's numbers |
| `AVG` | `SUM` over `COUNT` |
| `MIN` / `MAX` | Smallest / largest number, zeros included |
| `COUNT` | Rows in the (filtered) result set |
| `AVGNZ` | `SUM` over `COUNTNZ` |
| `MINNZ` | Smallest number that is not zero |
| `COUNTNZ` | Rows whose value is neither zero nor empty |

A `[copy]` button under the list puts the statistics on the clipboard as `NAME<tab>value` lines, using the plain numbers rather than the displayed ones.

Values are read straight off the model, so they are in the attribute's own unit — `formats` and `rawFormats` are display concerns and are not applied while summarizing. The numeric statistics are then rendered through the column's `formats` callback, which is therefore called with an aggregate and no row (`$format($value, null)`); one that needs the row it came from falls back to a plain number. `COUNT` and `COUNTNZ` are never formatted.

Only columns whose every value is a number get the numeric statistics; the rest have nothing to offer but their two row counts, and the menu lists only those.

Nothing outside `statsAttributes` is summarized: those columns carry no menu, and a browser naming none of them never runs the extra query.

Statistics are loaded after the table itself, and refresh whenever the filters change. Relations the query eager loads, through `with` or in the model's summary method, are loaded in chunks rather than row by row. Above `statsLimit` rows none are computed, and the menu reads *"To show stats, reduce results below 5,000 using filters."*

```php
<livewire:table-model-browser
    model="App\Models\Order"
    :viewAttributes="['symbol' => 'Symbol', 'price' => 'Total', 'customer.name' => 'Ordered by']"
    :formats="['price' => 'formatCurrency']"
    :statsAttributes="['price']"
/>
```

## License & Commercial Terms

### License

Copyright © 2026 **Internet Guru**

This software is licensed under the [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)](http://creativecommons.org/licenses/by-nc-sa/4.0/) license.

> **Disclaimer:** This software is provided "as is", without warranty of any kind, express or implied. In no event shall the authors or copyright holders be liable for any claim, damages or other liability.

### Commercial Use

The standard CC BY-NC-SA license prohibits commercial use. If you wish to use this software in a commercial environment or product, we offer **flexible commercial licenses** tailored to:

* Your company size.
* The nature of your project.
* Your specific integration needs.

**Note:** In many instances (especially for startups or small-scale tools), this may result in no fees being charged at all. Please contact us to obtain written permission or a commercial agreement.

**Contact for Licensing:** [info@internetguru.io](mailto:info@internetguru.io)

### Professional Services

Are you looking to get the most out of this project? We are available for:

* **Custom Development:** Tailoring the software to your specific requirements.
* **Integration & Support:** Helping your team implement and maintain the solution.
* **Training & Workshops:** Seminars and hands-on workshops for your developers.

Reach out to us at [info@internetguru.io](mailto:info@internetguru.io) — we are more than happy to assist you!
