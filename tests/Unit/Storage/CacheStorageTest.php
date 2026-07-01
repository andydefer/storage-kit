<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Tests\Unit\Storage;

use AndyDefer\StorageKit\Enums\CacheDriver;
use AndyDefer\StorageKit\Records\CacheConfigRecord;
use AndyDefer\StorageKit\Records\CacheStorageStatsRecord;
use AndyDefer\StorageKit\Storage\CacheStorage;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use PHPUnit\Framework\TestCase;

final class CacheStorageTest extends TestCase
{
    private CacheStorage $cache;

    protected function setUp(): void
    {
        $this->cache = new CacheStorage;
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    // ============================================================
    // Basic Operations
    // ============================================================

    public function test_set_and_get(): void
    {
        // Arrange
        $key = 'test_key';
        $value = ['name' => 'John', 'age' => 30];

        // Act
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        // Act
        $result = $this->cache->get('non_existent', 'default');

        // Assert
        $this->assertSame('default', $result);
    }

    public function test_get_returns_null_when_key_not_found_and_no_default(): void
    {
        // Act
        $result = $this->cache->get('non_existent');

        // Assert
        $this->assertNull($result);
    }

    public function test_set_overwrites_existing_key(): void
    {
        // Arrange
        $key = 'test_key';
        $firstValue = 'first_value';
        $secondValue = 'second_value';

        // Act
        $this->cache->set($key, $firstValue);
        $this->cache->set($key, $secondValue);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($secondValue, $result);
    }

    // ============================================================
    // Batch Operations
    // ============================================================

    public function test_get_multiple_returns_all_requested_keys(): void
    {
        // Arrange
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        // Act
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);

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
        $this->cache->set('key1', 'value1');

        // Act
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);

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
        $this->cache->setMultiple($items);

        // Assert
        $this->assertSame('value1', $this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
        $this->assertSame('value3', $this->cache->get('key3'));
    }

    public function test_delete_multiple_removes_all_specified_keys(): void
    {
        // Arrange
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        // Act
        $this->cache->deleteMultiple(['key1', 'key2']);

        // Assert
        $this->assertFalse($this->cache->exists('key1'));
        $this->assertFalse($this->cache->exists('key2'));
        $this->assertTrue($this->cache->exists('key3'));
    }

    // ============================================================
    // Existence Checks
    // ============================================================

    public function test_exists_returns_true_for_existing_key(): void
    {
        // Arrange
        $this->cache->set('existing_key', 'value');

        // Act
        $result = $this->cache->exists('existing_key');

        // Assert
        $this->assertTrue($result);
    }

    public function test_exists_returns_false_for_non_existent_key(): void
    {
        // Act
        $result = $this->cache->exists('non_existent_key');

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
        $this->cache->set($key, 'value');

        // Act
        $result = $this->cache->delete($key);

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($this->cache->exists($key));
    }

    public function test_delete_returns_false_for_non_existent_key(): void
    {
        // Act
        $result = $this->cache->delete('non_existent_key');

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================
    // Clear
    // ============================================================

    public function test_clear_removes_all_data(): void
    {
        // Arrange
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        // Act
        $this->cache->clear();

        // Assert
        $this->assertFalse($this->cache->exists('key1'));
        $this->assertFalse($this->cache->exists('key2'));
        $this->assertFalse($this->cache->exists('key3'));
    }

    // ============================================================
    // TTL Support
    // ============================================================

    public function test_set_with_ttl_expires_after_ttl(): void
    {
        // Arrange
        $key = 'expiring_key';

        // Act
        $this->cache->setWithTTL($key, 'value', 2);

        // Assert
        $this->assertTrue($this->cache->exists($key));

        sleep(3);

        $this->assertNull($this->cache->get($key));
    }

    public function test_set_ttl_after_set_updates_expiration(): void
    {
        // Arrange
        $key = 'ttl_after_set';

        // Act
        $this->cache->set($key, 'value');
        $this->cache->setTTL($key, 2);

        // Assert
        $this->assertTrue($this->cache->exists($key));

        sleep(3);

        $this->assertNull($this->cache->get($key));
    }

    public function test_very_short_ttl_expires_correctly(): void
    {
        // Arrange
        $key = 'very_short_ttl';

        // Act
        $this->cache->setWithTTL($key, 'value', 1);

        // Assert
        $this->assertTrue($this->cache->exists($key));

        sleep(2);

        $this->assertNull($this->cache->get($key));
    }

    // ============================================================
    // Statistics
    // ============================================================

    public function test_get_stats_returns_statistics(): void
    {
        // Arrange
        $this->cache->get('non_existent');
        $this->cache->set('key1', 'value1');
        $this->cache->get('key1');

        // Act
        $stats = $this->cache->getStats();

        // Assert
        $this->assertInstanceOf(CacheStorageStatsRecord::class, $stats);
        $this->assertSame(CacheDriver::FILES, $stats->driver);
        $this->assertSame(1, $stats->hits);
        $this->assertSame(1, $stats->misses);
        $this->assertSame(1, $stats->sets);
        $this->assertGreaterThanOrEqual(0, $stats->deletes);
        $this->assertGreaterThanOrEqual(0, $stats->items_count);
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
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_stores_integer_value(): void
    {
        // Arrange
        $key = 'integer_key';
        $value = 42;

        // Act
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_stores_boolean_value(): void
    {
        // Arrange
        $key = 'boolean_key';
        $value = true;

        // Act
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertTrue($result);
    }

    public function test_stores_array_value(): void
    {
        // Arrange
        $key = 'array_key';
        $value = ['a' => 1, 'b' => 2, 'c' => 3];

        // Act
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_stores_nested_array_value(): void
    {
        // Arrange
        $key = 'nested_key';
        $value = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
                'preferences' => ['dark_mode' => true],
            ],
        ];

        // Act
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    // ============================================================
    // Key Prefix
    // ============================================================

    public function test_cache_key_prefix_is_applied(): void
    {
        // Arrange
        $cache = new CacheStorage(
            driver: CacheDriver::FILES,
            config: null,
            cacheKeyPrefix: 'app_'
        );

        // Act
        $cache->set('test', 'value');
        $result = $cache->get('test');

        // Assert
        $this->assertSame('value', $result);
    }

    // ============================================================
    // Driver Access
    // ============================================================

    public function test_get_driver_returns_driver_instance(): void
    {
        // Act
        $driver = $this->cache->getDriver();

        // Assert
        $this->assertInstanceOf(ExtendedCacheItemPoolInterface::class, $driver);
    }

    public function test_get_driver_name_returns_driver_name(): void
    {
        // Act
        $name = $this->cache->getDriverName();

        // Assert
        $this->assertSame('Files', $name);
    }

    // ============================================================
    // Sqlite Driver
    // ============================================================

    public function test_sqlite_driver_works_correctly(): void
    {
        // Arrange
        $config = new CacheConfigRecord(
            path: sys_get_temp_dir().'/storage_sqlite_test.sqlite'
        );

        // Act
        $cache = new CacheStorage(CacheDriver::SQLITE, $config);
        $cache->set('sqlite_key', 'sqlite_value');
        $result = $cache->get('sqlite_key');

        // Assert
        $this->assertSame('sqlite_value', $result);
        $cache->clear();
    }

    // ============================================================
    // Custom Configuration
    // ============================================================

    public function test_custom_config_is_applied(): void
    {
        // Arrange
        $config = new CacheConfigRecord(
            path: sys_get_temp_dir().'/custom_cache'
        );

        // Act
        $cache = new CacheStorage(CacheDriver::FILES, $config);
        $cache->set('custom_key', 'custom_value');
        $result = $cache->get('custom_key');

        // Assert
        $this->assertSame('custom_value', $result);
        $cache->clear();
    }
}
