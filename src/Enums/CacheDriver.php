<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Enums;

/**
 * Supported cache drivers for PhpFastCache backend.
 *
 * These drivers determine how cached data is stored and retrieved.
 */
enum CacheDriver: string
{
    /**
     * File-based cache storage using the local filesystem.
     * Suitable for development and small to medium deployments.
     */
    case FILES = 'Files';

    /**
     * SQLite database-based cache storage.
     * Offers better performance than Files for larger datasets.
     */
    case SQLITE = 'Sqlite';
}
