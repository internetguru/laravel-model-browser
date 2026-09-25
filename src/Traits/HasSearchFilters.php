<?php

namespace Internetguru\ModelBrowser\Traits;

use InvalidArgumentException;

/**
 * Provides Gmail-style search query parsing and filter management for Livewire components.
 *
 * Requires the using class to have:
 * - public string $searchQuery
 * - public array $filterValues
 * - public array $filterConfig
 *
 * Override onFiltersChanged() to hook into filter/search updates (e.g. save to session/settings).
 */
trait HasSearchFilters
{
    // Search query security limits
    public const SEARCH_MAX_LENGTH = 500;

    public const SEARCH_MAX_TERMS = 20;

    /**
     * The filter value standing for "this filter has no value at all", written as a bare
     * `attribute:` in the search query (`attribute:""` reads the same).
     */
    public const FILTER_EMPTY = '""';

    /**
     * Filter names are kebab case, e.g. `created-by`.
     */
    public const FILTER_NAME_PATTERN = '[a-z]+(?:-[a-z]+)*';

    public function mountHasSearchFilters(): void
    {
        foreach (array_keys($this->filterConfig) as $name) {
            if (! preg_match('/^' . self::FILTER_NAME_PATTERN . '$/D', (string) $name)) {
                throw new InvalidArgumentException("Filter name '{$name}' must be kebab case, e.g. 'created-by'.");
            }
        }
    }

    /**
     * Hook called after filters/search are changed.
     * Override this in the using class to persist or react to changes.
     */
    protected function onFiltersChanged(): void
    {
        // Override in using class
    }

    /**
     * Apply search query — parse, sync filter fields, notify.
     */
    public function applySearch(): void
    {
        $this->searchQuery = $this->sanitizeSearchQuery($this->searchQuery);

        foreach ($this->filterConfig as $attr => $config) {
            $this->filterValues[$attr] = '';
        }
        foreach ($this->parseSearchTerms($this->searchQuery) as $term) {
            if ($term['key'] !== null && isset($this->filterConfig[$term['key']])) {
                $this->filterValues[$term['key']] = $term['value'];
            }
        }

        $this->onFiltersChanged();
    }

    /**
     * Apply filters — build search query from filter values, notify.
     */
    public function applyFilters(): void
    {
        // Preserve existing free text terms from search query
        $freeText = array_filter(
            $this->parseSearchTerms($this->searchQuery),
            fn ($t) => $t['key'] === null
        );

        $parts = [];
        foreach ($this->filterValues as $attr => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $this->formatSearchTerm($attr, $value);
            }
        }
        foreach ($freeText as $term) {
            $parts[] = ($term['exact'] ?? false) ? '"' . $term['value'] . '"' : $term['value'];
        }

        $this->searchQuery = implode(' ', $parts);
        $this->onFiltersChanged();
    }

    /**
     * Clear all filters and search query, notify.
     */
    public function clearFilters(): void
    {
        foreach ($this->filterValues as $key => $value) {
            $this->filterValues[$key] = '';
        }
        $this->searchQuery = '';
        $this->onFiltersChanged();
    }

    /**
     * Sanitize search query input against abuse.
     */
    protected function sanitizeSearchQuery(string $query): string
    {
        $query = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $query);
        $query = preg_replace('/\s+/', ' ', trim($query));

        return mb_substr($query, 0, self::SEARCH_MAX_LENGTH);
    }

    /**
     * Parse search query into structured terms.
     * Returns array of ['key' => string|null, 'value' => string, 'exact' => bool]
     */
    protected function parseSearchTerms(string $query): array
    {
        $query = $this->sanitizeSearchQuery($query);
        $terms = [];

        // A bare `key:` ends at a space or the end of the query, so an unfinished `key:"two` stays free text
        $remaining = preg_replace_callback('/(?<![\w-])(' . self::FILTER_NAME_PATTERN . '):(?:"([^"]*)"|([^\s"]+)|(?=\s|$))/', function ($match) use (&$terms) {
            $key = $match[1];
            // `key:""` is written with quotes, `key:value` without — the branches of
            // the pattern above, told apart by what follows the colon.
            $quoted = str_starts_with(substr($match[0], strlen($key) + 1), '"');
            $value = $quoted ? ($match[2] ?? '') : ($match[3] ?? '');
            $value = mb_substr(trim($value), 0, 255);
            $isConfigured = isset($this->filterConfig[$key]);
            if ($value === '') {
                // `key:` and `key:""` search for rows the filter finds nothing on. For a key
                // that is no filter, a bare `key:` stays free text and `key:""` is dropped.
                if ($isConfigured) {
                    $terms[] = ['key' => $key, 'value' => static::FILTER_EMPTY, 'exact' => false];
                } elseif (! $quoted) {
                    $terms[] = ['key' => null, 'value' => $match[0], 'exact' => false];
                }

                return '';
            }
            if ($isConfigured) {
                $terms[] = ['key' => $key, 'value' => $value, 'exact' => false];
            } else {
                $terms[] = ['key' => null, 'value' => $match[0], 'exact' => false];
            }

            return '';
        }, $query);

        $remaining = preg_replace_callback('/"([^"]*)"/', function ($match) use (&$terms) {
            $value = mb_substr(trim($match[1]), 0, 255);
            if ($value !== '') {
                $terms[] = ['key' => null, 'value' => $value, 'exact' => true];
            }

            return '';
        }, $remaining);

        foreach (preg_split('/\s+/', trim($remaining), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $word = mb_substr(trim($word), 0, 255);
            if ($word !== '') {
                $terms[] = ['key' => null, 'value' => $word, 'exact' => false];
            }
        }

        return array_slice($terms, 0, self::SEARCH_MAX_TERMS);
    }

    /**
     * Write one filter term of the search query: `key:value`, `key:"two words"`, or a bare `key:` for no value.
     */
    protected function formatSearchTerm(string $key, string $value): string
    {
        return match (true) {
            $value === static::FILTER_EMPTY => "{$key}:",
            str_contains($value, ' ') => "{$key}:\"{$value}\"",
            default => "{$key}:{$value}",
        };
    }

    /**
     * Build search query string from current filter values.
     */
    protected function buildSearchQuery(): string
    {
        $parts = [];

        foreach ($this->filterValues as $attr => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $parts[] = $this->formatSearchTerm($attr, $value);
        }

        return implode(' ', $parts);
    }

    /**
     * Build search query string from structured terms array.
     */
    protected function buildSearchQueryFromTerms(array $terms): string
    {
        $parts = [];
        foreach ($terms as $term) {
            if ($term['key'] === null) {
                $parts[] = ($term['exact'] ?? false) ? '"' . $term['value'] . '"' : $term['value'];
            } else {
                $parts[] = $this->formatSearchTerm($term['key'], $term['value']);
            }
        }

        return implode(' ', $parts);
    }
}
