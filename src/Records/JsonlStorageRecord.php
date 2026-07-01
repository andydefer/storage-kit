<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Data record for JSONL storage persistence.
 *
 * Represents a single entry stored in the JSONL format with optional
 * expiration and context metadata.
 */
final class JsonlStorageRecord extends AbstractRecord
{
    public function __construct(
        /** Unique identifier for the stored value. */
        public readonly string $key,

        /** The actual data to store (will be JSON encoded). */
        public readonly mixed $value,

        /** Optional context for data isolation. */
        public readonly ?string $context = null,

        /** Expiration timestamp in ISO format. Null means never expires. */
        public readonly ?string $expires_at = null,
    ) {}
}
