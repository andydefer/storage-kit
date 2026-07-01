<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Tests\Unit\Storage;

use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\StorageKit\Records\JsonlStorageStatsRecord;
use AndyDefer\StorageKit\Storage\JsonlStorage;
use PHPUnit\Framework\TestCase;

final class JsonlStorageTest extends TestCase
{
    private JsonlStorage $storage;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/storage_test_'.uniqid();
        $this->storage = new JsonlStorage($this->tempDir, 3600, 2);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory.DIRECTORY_SEPARATOR.$file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    // ============================================================
    // Basic Operations
    // ============================================================

    public function test_set_and_get(): void
    {
        // Arrange
        $key = 'user_123';
        $value = ['name' => 'John Doe', 'email' => 'john@example.com'];

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        // Act
        $result = $this->storage->get('non_existent_key', 'default_value');

        // Assert
        $this->assertSame('default_value', $result);
    }

    public function test_get_returns_null_when_key_not_found_and_no_default(): void
    {
        // Act
        $result = $this->storage->get('non_existent_key');

        // Assert
        $this->assertNull($result);
    }

    public function test_set_overwrites_existing_key(): void
    {
        // Arrange
        $key = 'test_key';
        $firstValue = ['data' => 'first'];
        $secondValue = ['data' => 'second'];

        // Act
        $this->storage->set($key, $firstValue);
        $this->storage->set($key, $secondValue);
        $result = $this->storage->get($key);

        // Assert
        $this->assertSame($secondValue, $result);
    }

    // ============================================================
    // Batch Operations
    // ============================================================

    public function test_get_multiple_returns_all_requested_keys(): void
    {
        // Arrange
        $this->storage->set('key1', 'value1');
        $this->storage->set('key2', 'value2');
        $this->storage->set('key3', 'value3');

        // Act
        $result = $this->storage->getMultiple(['key1', 'key2', 'key3']);

        // Assert
        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], $result);
    }

    public function test_get_multiple_returns_null_for_missing_keys(): void
    {
        // Arrange
        $this->storage->set('key1', 'value1');

        // Act
        $result = $this->storage->getMultiple(['key1', 'key2', 'key3']);

        // Assert
        $this->assertSame([
            'key1' => 'value1',
            'key2' => null,
            'key3' => null,
        ], $result);
    }

    public function test_set_multiple_stores_all_values(): void
    {
        // Arrange
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // Act
        $this->storage->setMultiple($items);

        // Assert
        $this->assertSame('value1', $this->storage->get('key1'));
        $this->assertSame('value2', $this->storage->get('key2'));
        $this->assertSame('value3', $this->storage->get('key3'));
    }

    public function test_delete_multiple_removes_all_specified_keys(): void
    {
        // Arrange
        $this->storage->set('key1', 'value1');
        $this->storage->set('key2', 'value2');
        $this->storage->set('key3', 'value3');

        // Act
        $this->storage->deleteMultiple(['key1', 'key2']);

        // Assert
        $this->assertFalse($this->storage->exists('key1'));
        $this->assertFalse($this->storage->exists('key2'));
        $this->assertTrue($this->storage->exists('key3'));
    }

    // ============================================================
    // Existence Checks
    // ============================================================

    public function test_exists_returns_true_for_existing_key(): void
    {
        // Arrange
        $key = 'existing_key';
        $this->storage->set($key, ['data' => 'test']);

        // Act
        $result = $this->storage->exists($key);

        // Assert
        $this->assertTrue($result);
    }

    public function test_exists_returns_false_for_non_existent_key(): void
    {
        // Act
        $result = $this->storage->exists('non_existent_key');

        // Assert
        $this->assertFalse($result);
    }

    public function test_exists_returns_false_for_expired_key(): void
    {
        // Arrange
        $storage = new JsonlStorage($this->tempDir, 1, 2);
        $key = 'expiring_key';
        $storage->set($key, ['data' => 'test']);

        // Act
        sleep(2);
        $result = $storage->exists($key);

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================
    // Deletion
    // ============================================================

    public function test_delete_removes_existing_key(): void
    {
        // Arrange
        $key = 'key_to_delete';
        $this->storage->set($key, ['data' => 'test']);

        // Act
        $result = $this->storage->delete($key);

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($this->storage->exists($key));
    }

    public function test_delete_returns_false_for_non_existent_key(): void
    {
        // Act
        $result = $this->storage->delete('non_existent_key');

        // Assert
        $this->assertFalse($result);
    }

    public function test_delete_on_expired_key_removes_file(): void
    {
        // Arrange
        $storage = new JsonlStorage($this->tempDir, 1, 2);
        $key = 'expired_key';
        $storage->set($key, ['data' => 'test']);

        // Act
        sleep(2);
        $storage->get($key);
        $result = $storage->exists($key);

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================
    // Clear
    // ============================================================

    public function test_clear_removes_all_data(): void
    {
        // Arrange
        $this->storage->set('key1', ['data' => 'value1']);
        $this->storage->set('key2', ['data' => 'value2']);
        $this->storage->set('key3', ['data' => 'value3']);

        // Act
        $this->storage->clear();

        // Assert
        $this->assertFalse($this->storage->exists('key1'));
        $this->assertFalse($this->storage->exists('key2'));
        $this->assertFalse($this->storage->exists('key3'));
    }

    // ============================================================
    // Clean Expired
    // ============================================================

    public function test_clean_expired_removes_only_expired_entries(): void
    {
        // Arrange
        $storage = new JsonlStorage($this->tempDir, 1, 2);
        $storage->set('will_expire', ['data' => 'expired']);
        $storage->setTTL(3600);
        $storage->set('will_stay', ['data' => 'valid']);

        // Act
        sleep(2);
        $deletedCount = $storage->cleanExpired();

        // Assert
        $this->assertSame(1, $deletedCount);
        $this->assertFalse($storage->exists('will_expire'));
        $this->assertTrue($storage->exists('will_stay'));
    }

    // ============================================================
    // State Management
    // ============================================================

    public function test_save_state_and_load_state(): void
    {
        // Arrange
        $key = 'app_state';
        $state = ['root' => ['children' => [], 'words' => ['laravel', 'php']]];

        // Act
        $this->storage->saveState($key, $state);
        $result = $this->storage->loadState($key);

        // Assert
        $this->assertSame($state, $result);
    }

    public function test_save_state_with_context_and_load_state_with_context(): void
    {
        // Arrange
        $key = 'app_state';
        $context = 'french';
        $state = ['root' => ['children' => [], 'words' => ['bonjour', 'salut']]];

        // Act
        $this->storage->saveState($key, $state, $context);
        $result = $this->storage->loadState($key, $context);

        // Assert
        $this->assertSame($state, $result);
    }

    public function test_load_state_returns_null_for_non_existent_key(): void
    {
        // Act
        $result = $this->storage->loadState('non_existent_state');

        // Assert
        $this->assertNull($result);
    }

    // ============================================================
    // TTL
    // ============================================================

    public function test_set_ttl_changes_expiration(): void
    {
        // Arrange
        $storage = new JsonlStorage($this->tempDir, 10, 2);
        $key = 'ttl_test';

        // Act
        $storage->setTTL(5);
        $storage->set($key, ['data' => 'test']);
        $resultBefore = $storage->get($key);

        // Assert
        $this->assertSame(['data' => 'test'], $resultBefore);
        $this->assertSame(5, $storage->getTTL());
    }

    // ============================================================
    // Statistics
    // ============================================================

    public function test_get_stats_returns_statistics(): void
    {
        // Arrange
        $this->storage->set('key1', ['data' => 'value1']);
        $this->storage->set('key2', ['data' => 'value2']);

        // Act
        $stats = $this->storage->getStats();

        // Assert
        $this->assertInstanceOf(JsonlStorageStatsRecord::class, $stats);
        $this->assertGreaterThanOrEqual(0, $stats->total_lines_processed);
        $this->assertGreaterThanOrEqual(0, $stats->processed_files);
        $this->assertSame($this->tempDir, $stats->base_path);
        $this->assertSame(3600, $stats->ttl);
    }

    // ============================================================
    // Service Access
    // ============================================================

    public function test_get_jsonl_service_returns_service_instance(): void
    {
        // Act
        $service = $this->storage->getJsonlService();

        // Assert
        $this->assertInstanceOf(JsonlService::class, $service);
    }

    // ============================================================
    // Key Sanitization
    // ============================================================

    public function test_sanitize_handles_special_characters_in_key(): void
    {
        // Arrange
        $key = 'user/with/slashes?and&special@chars';
        $value = ['data' => 'test'];

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    // ============================================================
    // Data Types
    // ============================================================

    public function test_stores_string_value(): void
    {
        // Arrange
        $key = 'string_key';
        $value = 'Hello World';

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertIsString($result);
        $this->assertSame($value, $result);
    }

    public function test_stores_integer_value(): void
    {
        // Arrange
        $key = 'integer_key';
        $value = 42;

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertIsInt($result);
        $this->assertSame($value, $result);
    }

    public function test_stores_boolean_value(): void
    {
        // Arrange
        $key = 'boolean_key';
        $value = true;

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_stores_array_value(): void
    {
        // Arrange
        $key = 'array_key';
        $value = ['a' => 1, 'b' => 2, 'c' => 3];

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($value, $result);
    }

    public function test_stores_nested_array_value(): void
    {
        // Arrange
        $key = 'nested_array_key';
        $value = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
                'preferences' => ['dark_mode' => true, 'notifications' => false],
            ],
        ];

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($value, $result);
    }
}
