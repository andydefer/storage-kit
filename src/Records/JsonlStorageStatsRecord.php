<?php

declare(strict_types=1);

namespace AndyDefer\StorageKit\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Statistics record for JSONL storage.
 *
 * Provides metrics about JSONL operations and storage usage.
 */
final class JsonlStorageStatsRecord extends AbstractRecord
{
    public function __construct(
        /** Total number of processed lines across all operations. */
        public readonly int $total_lines_processed,

        /** Number of files that have been processed. */
        public readonly int $processed_files,

        /** Base directory path where JSONL files are stored. */
        public readonly string $base_path,

        /** Current global Time-To-Live in seconds. */
        public readonly int $ttl,
    ) {}
}
