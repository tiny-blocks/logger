<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * Defines the contract for redacting sensitive information from structured log data.
 *
 * Each implementation is responsible for a specific redaction strategy (e.g., masking a field).
 * Redaction is applied recursively to nested arrays.
 */
interface Redaction
{
    /**
     * Redacts sensitive data from the given payload and returns the modified payload.
     *
     * @param array<string, mixed> $payload The payload to be redacted.
     * @return array<string, mixed> The redacted payload.
     */
    public function redact(array $payload): array;
}
