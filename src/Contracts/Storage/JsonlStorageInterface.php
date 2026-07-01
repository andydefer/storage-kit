<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Contracts\Storage;

use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\StorageKit\Records\JsonlStorageStatsRecord;

/**
 * Extended storage interface for JSONL-based persistence.
 *
 * Provides state management, TTL support, and expiration cleanup.
 */
interface JsonlStorageInterface extends StorageInterface
{
    /**
     * Persists an application state with context support.
     *
     * @param  string  $key  The storage key
     * @param  array<string, mixed>  $state  The state to persist
     * @param  string|null  $context  Optional context for isolation
     */
    public function saveState(string $key, array $state, ?string $context = null): void;

    /**
     * Retrieves a previously persisted state.
     *
     * @param  string  $key  The storage key
     * @param  string|null  $context  Optional context for isolation
     * @return array<string, mixed>|null The retrieved state or null
     */
    public function loadState(string $key, ?string $context = null): ?array;

    /**
     * Sets the global Time-To-Live for all stored data.
     *
     * @param  int  $seconds  TTL in seconds (0 means no expiration)
     */
    public function setTTL(int $seconds): void;

    /**
     * Returns the current global TTL.
     */
    public function getTTL(): int;

    /**
     * Removes all expired entries from the storage.
     *
     * @return int Number of removed entries
     */
    public function cleanExpired(): int;

    /**
     * Returns storage statistics.
     */
    public function getStats(): JsonlStorageStatsRecord;

    /**
     * Returns the underlying JSONL service instance.
     */
    public function getJsonlService(): JsonlService;
}
