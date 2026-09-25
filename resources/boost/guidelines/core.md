# Laravel Model Browser (internetguru/laravel-model-browser)

Livewire lists of Eloquent models as a table or cards, with Gmail-style search, a filter panel, sorting, column statistics and CSV export. The full reference is `vendor/internetguru/laravel-model-browser/README.md`. Note the namespace: `Internetguru\ModelBrowser`, with a lowercase `g`.

## Usage

- Render a list with `<livewire:table-model-browser model="App\Models\Order@summary" … />`, or `base-model-browser` for cards. `@summary` names a static method on the model returning the base query. Put eager loading and joins there, or pass `with`.
- Main props:
  - `viewAttributes`: column key → translated label; dot paths reach relations
  - `exportAttributes`: extra CSV columns, to-one relations only
  - `formats`, `rawFormats`, `alignments`
  - `defaultSortColumn` / `defaultSortDirection`, `enableSort`
  - `filters` + `filterSessionKey`
  - `statsAttributes` / `statsLimit`, `exportLimit`, `refreshInterval`
  - table only: `columnWidths`, `lightDarkStep`
- Labels are translation calls in the view, such as `__('summary.created_at')`.

## Formatters

- `formats` and `rawFormats` map an attribute to the **name of a global function**, defined in the application's `app/Support/helpers.php`. Blade components cannot be used there.
- The function is called as `fn($value, $item)`. `$item` is `null` when formatting a column statistic, so a formatter must cope without the row. It is **not called at all when the value is `null`**, so handle "empty" in the view's default rather than in the formatter.
- A `formats` function returns HTML, echoed unescaped, so escape user data (`e()`). For a label, return `toLabelHtml()` or `Label::html()` from laravel-common.
- A `rawFormats` function returns the plain value used for sorting and the CSV export. Give one to every column whose `formats` output is markup, including cast enums, so sorting and exports stay plain text.

## Filters

- Filter names (the `filters` keys) are kebab case, such as `created-by`; any other name throws on mount.
- Each `filters` entry has:
  - `type`: `string`, `number`, `date`, `date_from`, `date_to`, `number_from`, `number_to`, `options` or `checkbox`
  - `label`, and optionally `rules`, `url`, `timezone`
  - `column` (auto-applied), or `columns` for an OR group
  - `relation` (dot path, wrapped in `whereHas`)
  - `options` for the `options` type
- A `*_from` and `*_to` filter over the same `column`/`columns` and `relation` form one range: both bounds must hold for the same related row.
- A filter without `column`/`columns` is not applied automatically. Read it in the model's summary method through the `HasModelBrowserFilters` trait; the model's `$modelBrowserFilterSessionKey` must equal the component's `filterSessionKey`.
- The search syntax is `name:john`, `name:"John Doe"`, free text over the string filters, and `name:""` for rows with no value. The active query lives in the `q` URL parameter, so a list can be linked to with a filter applied.

## Views and assets

- Views are namespaced `model-browser::` and overridden in `resources/views/vendor/model-browser`. Sass comes from `@import 'ig::model-browser/main'`.
