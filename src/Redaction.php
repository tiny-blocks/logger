<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

/**
 * Defines the contract for redacting sensitive information from log data.
 */
interface Redaction
{
    /**
     * Redacts sensitive data from the given array and returns the modified data.
     *
     * @param array $data The data to be redacted.
     * @return array The redacted data.
     */
    public function redact(array $data): array;
}
