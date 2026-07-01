<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Configuration record for CacheStorage.
 *
 * Holds optional configuration settings for the cache backend.
 */
final class CacheConfigRecord extends AbstractRecord
{
    public function __construct(
        /**
         * Storage path for the cache backend.
         * For Files driver: directory path.
         * For Sqlite driver: database file path.
         */
        public readonly ?string $path = null,
    ) {}
}
