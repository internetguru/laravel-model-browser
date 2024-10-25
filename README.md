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

```sh
# Build the Docker image
docker build -t laravel-model-browser-test .
# Run the tests
docker run --rm laravel-model-browser-test
# Both steps combined
docker build -t laravel-model-browser-test . && docker run --rm laravel-model-browser-test
```

## Basic Usage

1. Add the `ModelBrowser` trait to your models:

    ```php
    use Internetguru\ModelBrowser\Traits\ModelBrowser;

    class User extends Model
    {
        use ModelBrowser;
    }
    ```

2. Show the model browser in your views:

    ```html
    <!-- Show the model browser in base view (cards) -->
    <livewire:base-model-browser model="App\Models\User" />
    <!-- Show the model browser in a table -->
    <livewire:table-model-browser model="App\Models\User" />
    ```

## Advanced Options

When using the `TableModelBrowser` component, you can customize its behavior by passing additional parameters.

Here are the advanced options you can use:

1. **`model`**: The model class to be used for browsing. Optionally, you can specify a method to be called on the model.
    ```php
    model="App\Models\User"
    // or
    model="App\Models\User@summary"
    ```

2. **`filterAttributes`**: An array of attributes that can be used to filter the data.
    ```php
    :filterAttributes="['created_at', 'name', 'email', 'last_login', 'last_activity', 'is_active']"
    ```

3. **`viewAttributes`**: An array of attributes that will be displayed in the table, with their corresponding translations used as labels.
    ```php
    :viewAttributes="[
        'created_at' => __('summary.created_at'),
        'name' => __('summary.name'),
        'email' => __('summary.email'),
        'last_login' => __('summary.last_login'),
        'last_activity' => __('summary.last_activity'),
        'is_active' => __('summary.is_active'),
    ]"
    ```

4. **`formats`**: An array of formatting functions for the attributes. You can specify a single function name or an array with `up` and `down` keys.
    ```php
    :formats="[
        'created_at' =>  [
            'up' => 'formatDateTime',
            'down' => 'globalFormatDown',
        ],
        'name' => 'formatUserDetailLink',
        'email' => 'formatEmailLink',
        'last_login' =>  [
            'up' => 'formatDateTime',
            'down' => 'globalFormatDown',
        ],
        'last_activity' =>  [
            'up' => 'formatDateTime',
            'down' => 'globalFormatDown',
        ],
        'is_active' => [
            'up' => 'formatBoolean',
            'down' => 'formatBooleanDown',
        ],
    ]"
    ```

5. **`alignments`**: An array of alignment settings for the attributes. You can specify `start`, `end`, or `center`.
    ```php
    :alignments="[
        'created_at' => 'start',
        'last_activity' => 'end',
        'is_active' => 'center',
    ]"
    ```

6. **`lightDarkStep`**: An integer value to control the light/dark step for table rows. It is used only for the table layout.
    ```php
    :lightDarkStep="2"
    ```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
