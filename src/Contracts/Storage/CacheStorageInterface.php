<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Contracts\Storage;

use AndyDefer\StorageKit\Records\CacheStorageStatsRecord;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;

/**
 * Extended storage interface for cache-based implementations.
 *
 * Provides TTL (Time-To-Live) support and cache statistics.
 */
interface CacheStorageInterface extends StorageInterface
{
    /**
     * Stores a value with a specific Time-To-Live.
     *
     * @param  string  $key  The storage key
     * @param  mixed  $value  The value to store
     * @param  int  $ttl  Time-To-Live in seconds
     */
    public function setWithTTL(string $key, mixed $value, int $ttl): void;

    /**
     * Updates the Time-To-Live of an existing key.
     *
     * @param  string  $key  The storage key
     * @param  int  $seconds  New Time-To-Live in seconds
     */
    public function setTTL(string $key, int $seconds): void;

    /**
     * Returns cache usage statistics.
     */
    public function getStats(): CacheStorageStatsRecord;

    /**
     * Returns the underlying PhpFastCache driver instance.
     */
    public function getDriver(): ExtendedCacheItemPoolInterface;

    /**
     * Returns the name of the underlying driver.
     *
     * @return string Driver name (e.g., 'Files', 'Sqlite')
     */
    public function getDriverName(): string;

    /**
     * Returns the current key prefix.
     */
    public function getCacheKeyPrefix(): string;

    /**
     * Sets the key prefix for all operations.
     *
     * @param  string  $prefix  The new prefix
     */
    public function setCacheKeyPrefix(string $prefix): void;

    /**
     * Removes all cached data.
     */
    public function clear(): void;
}
