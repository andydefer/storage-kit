<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Enums;

/**
 * Available storage system implementations.
 *
 * Each system provides different persistence and performance characteristics.
 */
enum StorageSystem: string
{
    /**
     * In-memory storage using PHP arrays.
     * Fastest, but data is lost when script ends.
     * Ideal for testing and short-lived data.
     */
    case MEMORY = 'memory';

    /**
     * Persistent storage using JSON Lines (JSONL) format.
     * Data is stored on disk and survives script execution.
     * Good for production use with moderate performance needs.
     */
    case JSONL = 'jsonl';

    /**
     * Cache-based storage using PhpFastCache.
     * Supports multiple backends (Files, Sqlite) with TTL support.
     * Best for high-performance production use.
     */
    case CACHE = 'cache';
}
