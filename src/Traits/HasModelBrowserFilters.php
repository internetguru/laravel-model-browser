<?php

namespace Internetguru\ModelBrowser\Traits;

use Exception;

/**
 * Trait for models to access ModelBrowser filters from session.
 *
 * Usage:
 * 1. Use this trait in your model
 * 2. Define $modelBrowserFilterSessionKey (or override getModelBrowserFilterSessionKey())
 * 3. Call getModelBrowserFilters() to get active filters
 * 4. Apply filters in your query method as needed
 *
 * Example:
 * ```php
 * class User extends Model {
 *     use HasModelBrowserFilters;
 *
 *     protected string $modelBrowserFilterSessionKey = 'user-browser-filters';
 *
 *     public static function summary() {
 *         $filters = (new static)->getModelBrowserFilters();
 *         $query = self::query();
 *
 *         if ($name = $filters->get('name')) {
 *             // Accent-insensitive matching (á=a, č=c, etc.)
 *             $query->whereLikeUnaccented('name', $name);
 *         }
 *         if ($status = $filters->get('status')) {
 *             $query->where('status', $status);
 *         }
 *
 *         return $query;
 *     }
 * }
 * ```
 */
trait HasModelBrowserFilters
{
    /**
     * Get the session key for model browser filters.
     * Override this method or define $modelBrowserFilterSessionKey property.
     */
    public function getModelBrowserFilterSessionKey(): string
    {
        if (property_exists($this, 'modelBrowserFilterSessionKey')) {
            return $this->modelBrowserFilterSessionKey;
        }

        throw new Exception('Define $modelBrowserFilterSessionKey property or override getModelBrowserFilterSessionKey() method in ' . static::class);
    }

    /**
     * Get all filters from session.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    public function getModelBrowserFilters(): \Illuminate\Support\Collection
    {
        $filters = session($this->getModelBrowserFilterSessionKey(), []);

        return collect($filters)->filter(fn ($value) => $value !== '' && $value !== null);
    }

    /**
     * Get a specific filter value from session.
     */
    public function getModelBrowserFilter(string $key, mixed $default = null): mixed
    {
        return $this->getModelBrowserFilters()->get($key, $default);
    }

    /**
     * Check if a specific filter is set and not empty.
     */
    public function hasModelBrowserFilter(string $key): bool
    {
        return $this->getModelBrowserFilters()->has($key);
    }

    /**
     * Check if any filters are active.
     */
    public function hasModelBrowserFilters(): bool
    {
        return $this->getModelBrowserFilters()->isNotEmpty();
    }
}
