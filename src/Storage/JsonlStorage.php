<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Storage;

use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpJsonl\Records\CacheJsonlRecord;
use AndyDefer\PhpJsonl\Strategies\KeyBasedPathStrategy;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\StorageKit\Contracts\Storage\JsonlStorageInterface;
use AndyDefer\StorageKit\Records\JsonlStorageStatsRecord;

/**
 * Persistent storage implementation using JSON Lines (JSONL) format.
 *
 * Each key is stored as a separate JSONL file with support for TTL and context.
 *
 * @example
 * $storage = new JsonlStorage('/var/data/storage', 3600);
 * $storage->set('user', ['name' => 'John']);
 * $user = $storage->get('user');
 */
final class JsonlStorage implements JsonlStorageInterface
{
    private JsonlService $service;

    private string $basePath;

    private int $ttl;

    /** @var array<string, string> */
    private array $filePathCache = [];

    public function __construct(
        string $basePath,
        int $ttl = 86400,
        int $hashLevels = 2
    ) {
        $this->basePath = rtrim($basePath, '/');
        $this->ttl = $ttl;

        $strategy = new KeyBasedPathStrategy($this->basePath, $hashLevels);
        $fileSystem = new FileSystemService;
        $context = new JsonlContext;

        $this->service = new JsonlService(
            pathStrategy: $strategy,
            fileSystem: $fileSystem,
            context: $context,
            defaultBufferSize: 1000,
        );
    }

    /**
     * Resolves the file path for a given key.
     *
     * Uses caching to avoid repeated path resolution.
     */
    private function resolveFilePath(string $key): string
    {
        if (! isset($this->filePathCache[$key])) {
            $record = new CacheJsonlRecord(
                key: $this->sanitizeKey($key),
                value: '',
                expires_at: null
            );
            $this->filePathCache[$key] = $this->service->getFilePath($record);
        }

        return $this->filePathCache[$key];
    }

    /**
     * Sanitizes a key for use as a filename.
     *
     * Replaces special characters with underscores.
     */
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $filePath = $this->resolveFilePath($key);

        if (! $this->service->fileExists($filePath)) {
            return $default;
        }

        $data = $this->service->getFirstLine($filePath);

        if ($data === null) {
            return $default;
        }

        if (isset($data['expires_at']) && $data['expires_at'] !== null) {
            $expiresAt = new \DateTime($data['expires_at']);
            if ($expiresAt < new \DateTime) {
                $this->delete($key);

                return $default;
            }
        }

        $decoded = json_decode($data['value'], true);

        return $decoded['value'] ?? $default;
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
        $this->delete($key);

        $expiresAt = $this->ttl > 0
            ? new DateTimeVO('+'.$this->ttl.' seconds')
            : null;

        $record = new CacheJsonlRecord(
            key: $this->sanitizeKey($key),
            value: json_encode(['value' => $value]),
            expires_at: $expiresAt,
        );

        $this->service->write($record);
    }

    public function setMultiple(array $items): void
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function delete(string $key): bool
    {
        $filePath = $this->resolveFilePath($key);

        if (! $this->service->fileExists($filePath)) {
            return false;
        }

        unset($this->filePathCache[$key]);

        return $this->service->deleteFile($filePath);
    }

    public function deleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function exists(string $key): bool
    {
        $filePath = $this->resolveFilePath($key);

        if (! $this->service->fileExists($filePath)) {
            return false;
        }

        $data = $this->service->getFirstLine($filePath);

        if ($data === null) {
            return false;
        }

        $record = CacheJsonlRecord::from($data);

        return ! $this->service->isExpired($record);
    }

    public function clear(): void
    {
        $fs = $this->service->getFileSystem();
        $this->clearDirectory($fs, $this->basePath);
        $this->filePathCache = [];
    }

    /**
     * Recursively removes all JSONL files from a directory.
     */
    private function clearDirectory(FileSystemInterface $fs, string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->clearDirectory($fs, $path);
                @rmdir($path);
            } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'jsonl') {
                $fs->delete($path);
            }
        }
    }

    public function cleanExpired(): int
    {
        $fs = $this->service->getFileSystem();
        $deletedCount = 0;
        $files = $this->findAllJsonlFiles($this->basePath);

        foreach ($files as $filePath) {
            $lines = $this->service->readAll($filePath);
            $validLines = [];
            $hasExpired = false;

            foreach ($lines as $line) {
                $isExpired = false;

                if (isset($line['expires_at']) && $line['expires_at'] !== null) {
                    $expiresAt = new \DateTime($line['expires_at']);
                    if ($expiresAt < new \DateTime) {
                        $isExpired = true;
                        $hasExpired = true;
                        $deletedCount++;
                    }
                }

                if (! $isExpired) {
                    $validLines[] = $line;
                }
            }

            if ($hasExpired) {
                if (empty($validLines)) {
                    $fs->delete($filePath);
                } else {
                    $content = '';
                    foreach ($validLines as $line) {
                        $content .= json_encode($line)."\n";
                    }
                    $fs->put($filePath, $content);
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Recursively finds all JSONL files in a directory.
     *
     * @return string[]
     */
    private function findAllJsonlFiles(string $directory): array
    {
        $files = [];

        if (! is_dir($directory)) {
            return $files;
        }

        $items = scandir($directory);

        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->findAllJsonlFiles($path));
            } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'jsonl') {
                $files[] = $path;
            }
        }

        return $files;
    }

    public function saveState(string $key, array $state, ?string $context = null): void
    {
        $storageKey = $context !== null ? $key.'_'.$context : $key;
        $this->set($storageKey, $state);
    }

    public function loadState(string $key, ?string $context = null): ?array
    {
        $storageKey = $context !== null ? $key.'_'.$context : $key;

        return $this->get($storageKey);
    }

    public function setTTL(int $seconds): void
    {
        $this->ttl = $seconds;
    }

    public function getTTL(): int
    {
        return $this->ttl;
    }

    public function getJsonlService(): JsonlService
    {
        return $this->service;
    }

    public function getStats(): JsonlStorageStatsRecord
    {
        $context = $this->service->getContext();

        return new JsonlStorageStatsRecord(
            total_lines_processed: $context->getTotalLinesProcessed(),
            processed_files: $context->getProcessedFiles()->count(),
            base_path: $this->basePath,
            ttl: $this->ttl,
        );
    }
}
