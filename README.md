# Laravel Model Browser

A Laravel package to browse models and show them in cards, tables, etc.

| Branch  | Status | Code Coverage |
| :------------- | :------------- | :------------- |
| Main | ![tests](https://github.com/internetguru/laravel-model-browser/actions/workflows/test.yml/badge.svg?branch=main) | ![coverage](https://raw.githubusercontent.com/internetguru/laravel-model-browser/refs/heads/badges/main-coverage.svg) |
| Staging | ![tests](https://github.com/internetguru/laravel-model-browser/actions/workflows/test.yml/badge.svg?branch=staging) | ![coverage](https://raw.githubusercontent.com/internetguru/laravel-model-browser/refs/heads/badges/staging-coverage.svg) |
| Dev | ![tests](https://github.com/internetguru/laravel-model-browser/actions/workflows/test.yml/badge.svg?branch=dev) | ![coverage](https://raw.githubusercontent.com/internetguru/laravel-model-browser/refs/heads/badges/dev-coverage.svg) |

## Installation

1. Install the package via Composer:

    ```sh
    # First time installation
    composer require internetguru/laravel-model-browser
    # For updating the package
    composer update internetguru/laravel-model-browser
    ```

2. Optionally publish the views and translations:

    ```sh
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
function formatDateTime($order, $value)
{
    return \Carbon\Carbon::parse($value)->format('d.m.Y H:i');
}

function formatCurrency($order, $value)
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

The filter system provides a search bar with Gmail-style query syntax and an expandable filter panel. Filters are persisted in the session and can be initialized from URL query parameters.

### Configuration

Pass an associative array to the `filters` parameter. Each key is a filter attribute name, and each value is a config array:

```php
:filters="[
    'from' => [
        'type' => 'date_from',
        'label' => 'From Date',
        'column' => 'created_at',
        'timezone' => 'Europe/Prague',
    ],
    'to' => [
        'type' => 'date_to',
        'label' => 'To Date',
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
    'priceFrom' => [
        'type' => 'number_from',
        'label' => 'Price From',
    ],
    'priceTo' => [
        'type' => 'number_to',
        'label' => 'Price To',
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

Note that `priceFrom` and `priceTo` have no `column` key — they are not auto-applied and must be handled manually in the model scope (see [HasModelBrowserFilters Trait](#hasmodelbrowserfilters-trait)).

### Filter Config Keys

| Key | Description |
|---|---|
| `type` | Filter type: `string`, `number`, `date`, `date_from`, `date_to`, `number_from`, `number_to`, `options` (default: `string`). **Note:** `date_to` interprets date-only values (without an explicit time) as end-of-day (23:59:59), so e.g. `to:2026-02-16` includes all records on Feb 16. When a specific time is provided, it is used as-is. |
| `label` | Display label in the filter panel |
| `column` | Database column name for auto-apply. **When set**, the filter is automatically applied to the query. **When omitted**, the filter is NOT auto-applied — use `HasModelBrowserFilters` trait for manual access. |
| `relation` | Eloquent relation name — wraps the filter in `whereHas()`. Supports dot-notation for nested relations. |
| `options` | Array of options for the `options` type (e.g. `['value' => 'Label']`) |
| `rules` | Custom Laravel validation rules (overrides default type-based rules) |
| `url` | URL query parameter name to initialize the filter from (takes priority over session) |
| `timezone` | Timezone for date filters — the parsed date value is shifted via `Carbon::shiftTimezone($tz)` (e.g. `'Europe/Prague'`) |

### Search Query Syntax

The search bar supports Gmail-style syntax:

- **Free text**: `john` — searches across all `string`-type filter columns (with `column` set)
- **Specific filter**: `name:john` — applies to the `name` filter
- **Quoted values**: `name:"John Doe"` — for values containing spaces
- **Combined**: `name:john from:2025-01-01` — all terms must match (AND)

### Auto-applied vs Manual Filters

Filters with a `column` key are **auto-applied** to the Eloquent query. Filters without `column` are stored in session but require manual application — useful for custom logic in model scopes:

```php
// Auto-applied filter (no manual code needed):
'name' => ['type' => 'string', 'label' => 'Name', 'column' => 'name']

// Manual filter (applied in your model scope via HasModelBrowserFilters):
'priceFrom' => ['type' => 'number_from', 'label' => 'Price From']
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
        $filters = (new static)->getModelBrowserFilters();

        // Manual filter: price is a computed sum of related charges
        if ($priceFrom = $filters->get('priceFrom')) {
            $query->whereRaw(
                '(SELECT SUM(amount) FROM charges WHERE charges.order_id = orders.id) >= ?',
                [$priceFrom * 100]
            );
        }
        if ($priceTo = $filters->get('priceTo')) {
            $query->whereRaw(
                '(SELECT SUM(amount) FROM charges WHERE charges.order_id = orders.id) <= ?',
                [$priceTo * 100]
            );
        }

        return $query;
    }
}
```

Available methods:

- `getModelBrowserFilters()` — returns a `Collection` of active filter values
- `getModelBrowserFilter(string $key, mixed $default = null)` — get a specific filter value
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

The URL parameters are automatically cleaned from the browser address bar after initialization.

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
        'from' => [
            'type' => 'date_from',
            'label' => __('summary.from_date'),
            'column' => 'created_at',
        ],
        'to' => [
            'type' => 'date_to',
            'label' => __('summary.to_date'),
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
        'priceFrom' => [
            'type' => 'number_from',
            'label' => __('summary.price_from'),
        ],
        'priceTo' => [
            'type' => 'number_to',
            'label' => __('summary.price_to'),
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
- `from`, `to`, `symbol`, `voucher`, `name`, `email` have `column` set → **auto-applied** to the query
- `priceFrom`, `priceTo` have no `column` → **manual filters** handled in `Order::summary()` via `HasModelBrowserFilters`
- `voucher` uses `relation` with dot-notation (`charges.voucher`) for nested `whereHas()` and `url` for URL initialization

## Features

- **Pagination** — Simple pagination with configurable per-page options (default: 20, 50, 100). Shows result range and total count (loaded asynchronously). Per-page preference is saved per authenticated user.
- **Auto-refresh** — Optional periodic data refresh via `refreshInterval` parameter.
- **Sorting** — Click column headers to sort ascending/descending or reset. Supports default sort column and direction.
- **CSV Export** — Download the current filtered and sorted data as a CSV file.
- **Fullscreen** — Toggle fullscreen mode for the table view.
- **Lazy Loading** — Components use `wire:lazy` for deferred rendering.

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
