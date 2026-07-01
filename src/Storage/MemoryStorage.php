<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Storage;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;

/**
 * In-memory storage implementation using a PHP array.
 *
 * Data is stored in RAM and is lost when the script ends.
 * Ideal for testing, development, and short-lived data.
 *
 * @example
 * $storage = new MemoryStorage();
 * $storage->set('user', ['name' => 'John']);
 * $user = $storage->get('user');
 */
final class MemoryStorage implements StorageInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function setMultiple(array $items): void
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function delete(string $key): bool
    {
        if (! array_key_exists($key, $this->data)) {
            return false;
        }

        unset($this->data[$key]);

        return true;
    }

    public function deleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
