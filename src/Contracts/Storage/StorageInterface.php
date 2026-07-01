<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Contracts\Storage;

/**
 * Unified storage interface for key-value data persistence.
 *
 * Provides basic CRUD operations with batch support for multiple keys.
 * Implementations can be memory-based, file-based, or cache-based.
 */
interface StorageInterface
{
    /**
     * Retrieves a value by its key.
     *
     * @param  string  $key  The storage key
     * @param  mixed  $default  Default value returned if key does not exist
     * @return mixed The stored value or the default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores a value with the given key.
     *
     * Overwrites any existing value for the same key.
     *
     * @param  string  $key  The storage key
     * @param  mixed  $value  The value to store
     */
    public function set(string $key, mixed $value): void;

    /**
     * Removes a value by its key.
     *
     * @param  string  $key  The storage key
     * @return bool True if the key existed and was removed, false otherwise
     */
    public function delete(string $key): bool;

    /**
     * Checks if a key exists in the storage.
     *
     * @param  string  $key  The storage key
     * @return bool True if the key exists, false otherwise
     */
    public function exists(string $key): bool;

    /**
     * Removes all stored data.
     */
    public function clear(): void;

    /**
     * Retrieves multiple values in a single operation.
     *
     * @param  string[]  $keys  List of keys to retrieve
     * @return array<string, mixed> Associative array of key-value pairs
     */
    public function getMultiple(array $keys): array;

    /**
     * Stores multiple values in a single operation.
     *
     * @param  array<string, mixed>  $items  Associative array of key-value pairs
     */
    public function setMultiple(array $items): void;

    /**
     * Removes multiple keys in a single operation.
     *
     * @param  string[]  $keys  List of keys to remove
     */
    public function deleteMultiple(array $keys): void;
}
