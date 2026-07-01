<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Tests\Unit\Storage;

use AndyDefer\StorageKit\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

final class MemoryStorageTest extends TestCase
{
    private MemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new MemoryStorage;
    }

    protected function tearDown(): void
    {
        $this->storage->clear();
    }

    // ============================================================
    // Basic Operations
    // ============================================================

    public function test_set_and_get(): void
    {
        // Arrange
        $key = 'user_123';
        $value = ['name' => 'John', 'age' => 30];

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        // Act
        $result = $this->storage->get('non_existent', 'default');

        // Assert
        $this->assertSame('default', $result);
    }

    public function test_get_returns_null_when_key_not_found_and_no_default(): void
    {
        // Act
        $result = $this->storage->get('non_existent');

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
        $this->storage->set('existing_key', 'value');

        // Act
        $result = $this->storage->exists('existing_key');

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

    // ============================================================
    // Deletion
    // ============================================================

    public function test_delete_removes_existing_key(): void
    {
        // Arrange
        $key = 'key_to_delete';
        $this->storage->set($key, 'value');

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

    public function test_delete_does_not_affect_other_keys(): void
    {
        // Arrange
        $this->storage->set('key1', 'value1');
        $this->storage->set('key2', 'value2');

        // Act
        $this->storage->delete('key1');

        // Assert
        $this->assertFalse($this->storage->exists('key1'));
        $this->assertTrue($this->storage->exists('key2'));
        $this->assertSame('value2', $this->storage->get('key2'));
    }

    // ============================================================
    // Clear
    // ============================================================

    public function test_clear_removes_all_data(): void
    {
        // Arrange
        $this->storage->set('key1', 'value1');
        $this->storage->set('key2', 'value2');
        $this->storage->set('key3', 'value3');

        // Act
        $this->storage->clear();

        // Assert
        $this->assertFalse($this->storage->exists('key1'));
        $this->assertFalse($this->storage->exists('key2'));
        $this->assertFalse($this->storage->exists('key3'));
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

    public function test_stores_float_value(): void
    {
        // Arrange
        $key = 'float_key';
        $value = 3.14;

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertIsFloat($result);
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
        $key = 'nested_key';
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

    public function test_stores_object_value(): void
    {
        // Arrange
        $key = 'object_key';
        $value = new \stdClass;
        $value->name = 'John';
        $value->age = 30;

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    public function test_stores_null_value(): void
    {
        // Arrange
        $key = 'null_key';
        $value = null;

        // Act
        $this->storage->set($key, $value);
        $result = $this->storage->get($key);

        // Assert
        $this->assertNull($result);
        $this->assertTrue($this->storage->exists($key));
    }

    public function test_get_returns_correct_type(): void
    {
        // Arrange
        $this->storage->set('string', 'hello');
        $this->storage->set('int', 123);
        $this->storage->set('float', 45.67);
        $this->storage->set('bool', true);
        $this->storage->set('array', [1, 2, 3]);
        $this->storage->set('null', null);

        // Assert
        $this->assertIsString($this->storage->get('string'));
        $this->assertIsInt($this->storage->get('int'));
        $this->assertIsFloat($this->storage->get('float'));
        $this->assertIsBool($this->storage->get('bool'));
        $this->assertIsArray($this->storage->get('array'));
        $this->assertNull($this->storage->get('null'));
    }

    public function test_storage_preserves_data_order(): void
    {
        // Arrange
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // Act
        foreach ($data as $key => $value) {
            $this->storage->set($key, $value);
        }

        // Assert
        $this->assertSame('value1', $this->storage->get('key1'));
        $this->assertSame('value2', $this->storage->get('key2'));
        $this->assertSame('value3', $this->storage->get('key3'));
    }
}
