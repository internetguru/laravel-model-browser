<?php

namespace Internetguru\ModelBrowser\Support;

use Closure;
use Internetguru\ModelBrowser\Exceptions\QueryTimeoutException;

/**
 * A time limit for the list's queries, checked from inside SQLite while it scans rows.
 *
 * PHP's own time limit ends the request with a fatal error that cannot be caught;
 * a query running past this deadline throws an exception the component can handle.
 */
class QueryDeadline
{
    protected static ?int $deadline = null;

    /**
     * Run the callback with the deadline set `$seconds` from now; 0 or less runs it without one.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function within(float $seconds, Closure $callback): mixed
    {
        if ($seconds <= 0) {
            return $callback();
        }

        $outer = self::$deadline;
        self::$deadline = hrtime(true) + (int) ($seconds * 1e9);

        try {
            return $callback();
        } finally {
            self::$deadline = $outer;
        }
    }

    public static function active(): bool
    {
        return self::$deadline !== null;
    }

    /**
     * @throws QueryTimeoutException when the deadline has passed
     */
    public static function check(): void
    {
        if (self::$deadline !== null && hrtime(true) > self::$deadline) {
            throw new QueryTimeoutException('The query ran past the model browser query timeout.');
        }
    }

    /**
     * Whether the exception, or one it wraps, is a passed deadline.
     */
    public static function caused(\Throwable $e): bool
    {
        for ($cause = $e; $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof QueryTimeoutException) {
                return true;
            }
        }

        return false;
    }
}
